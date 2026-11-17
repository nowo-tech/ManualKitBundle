<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

use function bin2hex;
use function file_put_contents;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

use const FILE_APPEND;

/**
 * 1.11: `nowo:manual:redirects` — missing targets, cycles, chains, rules that never apply and old slugs in link maps.
 */
final class RedirectsCommandTest extends KernelTestCase
{
    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        new Filesystem()->remove($this->directories);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        $manual = $options['manual'] ?? [];
        self::assertIsArray($manual);

        /* @var array<string, mixed> $manual */
        return new TestKernel(array_replace(ChapterRedirectsTest::CONFIG, $manual));
    }

    public function testTheDeployedMapWarnsAboutItsChainAndStrictFails(): void
    {
        $command = $this->tester();

        self::assertSame(Command::SUCCESS, $command->execute([]));
        $output = $command->getDisplay();
        self::assertMatchesRegularExpression('/warning\s+05-chain → 01-install\s+Chain: "01-install" is redirected again; served in one hop to "setup"/', $output);
        self::assertMatchesRegularExpression('/warning\s+manual\/faq\.md:17\s+Links to the old "01-install#overview": point it at "setup#overview"/', $output);
        self::assertMatchesRegularExpression('/warning\s+manual\/faq\.md:17\s+Links to the old "faq#old-question": point it at "faq#questions"/', $output);
        self::assertStringContainsString('0 error(s), 3 warning(s) in 15 redirect rule(s).', $output);
        self::assertSame(Command::FAILURE, $command->execute(['--strict' => true]));
    }

    public function testOldSlugsInALinkMapAreReported(): void
    {
        $command = $this->tester();

        self::assertSame(Command::SUCCESS, $command->execute(['--route-links' => ['test.route_links', 'missing.parameter']]));
        $output = $command->getDisplay();
        self::assertStringContainsString('test.route_links routes.app_setup.path', $output);
        self::assertStringContainsString('Links to the old "01-install": point it at "setup" directly', $output);
        self::assertStringContainsString('Links to the old "04-split#docker": point it at "deploy#docker"', $output);
        self::assertStringContainsString('Links to the old "faq#old-question": point it at "faq#questions"', $output);
        self::assertStringNotContainsString('app_current', $output, 'a current link is fine');
        self::assertStringNotContainsString('app_nested', $output);
        self::assertStringContainsString('0 error(s), 6 warning(s)', $output);
    }

    public function testAChainThroughAnExistingChapterIsNamed(): void
    {
        $command = $this->tester(['redirects' => ['09-via-faq' => 'faq', 'faq' => 'setup']]);

        self::assertSame(Command::FAILURE, $command->execute([]));
        $output = $command->getDisplay();
        self::assertStringContainsString('"faq" is still a chapter: the redirect never applies.', $output);
        self::assertMatchesRegularExpression('/warning\s+06-merged → faq#legacy\s+The chain passes through the existing chapter "faq": served to "faq#legacy", not "setup#legacy"\./', $output);
    }

    public function testACleanMapPasses(): void
    {
        // The fixture manual without its old links.
        $directory = $this->manualCopy();
        file_put_contents($directory . '/faq.md', "# FAQ\n\n## Questions\n\n## Legacy\n\nSee [setup](setup.md#basics).\n");
        $command = $this->tester(['directory' => $directory]);

        self::assertSame(Command::SUCCESS, $command->execute(['--file' => $this->file("redirects:\n  old: setup\n  'old#x': 'setup#basics'\n  'faq#q': 'faq#legacy'\n")]));
        self::assertStringContainsString('3 redirect rule(s) checked: every target exists, no chain, no old slug', $command->getDisplay());
    }

    public function testAFileWithMissingTargetsCyclesAndDeadRulesFails(): void
    {
        $command = $this->tester();
        $file    = $this->file(<<<'YAML'
            setup: faq
            gone: nowhere
            moved: 'faq#missing'
            'orphan#x': 'faq#legacy'
            a: b
            b: a
            bad: 'https://evil.example'
            admin: 'faq#internal'
            YAML);

        self::assertSame(Command::FAILURE, $command->execute(['--file' => $file, '--route-links' => []]));
        $output = $command->getDisplay();
        self::assertStringContainsString('"setup" is still a chapter: the redirect never applies.', $output);
        self::assertStringContainsString('Target chapter "nowhere" does not exist.', $output);
        self::assertStringContainsString('Target anchor "missing" is not in chapter "faq".', $output);
        self::assertStringContainsString('"orphan" is neither a chapter nor redirected: this anchor rule never applies.', $output);
        self::assertStringContainsString('Redirect cycle: a → b → a.', $output);
        self::assertStringContainsString('targets are internal chapter slugs', $output);
        self::assertStringNotContainsString('"internal" is not in chapter', $output, 'anchors of admin blocks count');
    }

    public function testUnreadableFilesFail(): void
    {
        $command = $this->tester();

        self::assertSame(Command::FAILURE, $command->execute(['--file' => '/nowhere/redirects.yaml']));
        self::assertStringContainsString('File /nowhere/redirects.yaml not found.', $command->getDisplay());
        self::assertSame(Command::FAILURE, $command->execute(['--file' => $this->file("a: [\n")]));
        self::assertStringContainsString('cannot be parsed', $command->getDisplay());
        self::assertSame(Command::FAILURE, $command->execute(['--file' => $this->file("just text\n")]));
        self::assertStringContainsString('must hold a mapping of old → new paths.', $command->getDisplay());
    }

    public function testWithoutAMapTheChapterLinksAreStillChecked(): void
    {
        // The default fixture: es/README.md links 01-using.md#filters, and the Spanish 01-using lacks the stable anchor.
        $command = $this->tester(['directory' => '%kernel.project_dir%/tests/Fixtures/manual', 'redirects' => []]);

        self::assertSame(Command::FAILURE, $command->execute([]));
        $output = $command->getDisplay();
        self::assertMatchesRegularExpression('/error\s+manual\/es\/README\.md:4\s+Links to the anchor "filters", which is not in chapter "01-using" \(es\)/', $output);
        self::assertStringContainsString('1 error(s), 0 warning(s) in 0 redirect rule(s).', $output);
    }

    public function testBrokenChapterLinksAreErrorsWithTheirFileAndLine(): void
    {
        $directory = $this->manualCopy();
        file_put_contents($directory . '/setup.md', "\nSee [gone](nope.md) and [bad](faq.md#nowhere).\n\n```md\n[ignored](nope2.md)\n```\n", FILE_APPEND);
        file_put_contents($directory . '/es/setup.md', "\n[Despliegue](deploy.md#nein)\n", FILE_APPEND);
        $command = $this->tester(['directory' => $directory]);

        self::assertSame(Command::FAILURE, $command->execute([]));
        $output = $command->getDisplay();
        self::assertMatchesRegularExpression('/error\s+manual\/setup\.md:11\s+Links to "nope\.md", which is not a chapter\./', $output);
        self::assertMatchesRegularExpression('/error\s+manual\/setup\.md:11\s+Links to the anchor "nowhere", which is not in chapter "faq"\./', $output);
        self::assertMatchesRegularExpression('/error\s+manual\/es\/setup\.md:10\s+Links to the anchor "nein", which is not in chapter "deploy" \(es\)\./', $output);
        self::assertStringNotContainsString('nope2', $output, 'fenced code is not checked');
        self::assertStringContainsString('3 error(s)', $output);
    }

    /**
     * @param array<string, mixed>|null $manual
     */
    private function tester(?array $manual = null): CommandTester
    {
        self::ensureKernelShutdown();
        self::bootKernel($manual === null ? [] : ['manual' => $manual]);

        return new CommandTester(new Application(self::$kernel ?? self::fail('kernel'))->find('nowo:manual:redirects'));
    }

    /** A copy of the redirects fixture manual (its own kernel: the directory is part of the cache key). */
    private function manualCopy(): string
    {
        $root = sys_get_temp_dir() . '/manual-lint-' . bin2hex(random_bytes(4));
        new Filesystem()->mirror(__DIR__ . '/../Fixtures/redirects/manual', $root . '/manual');
        $this->directories[] = $root;

        return $root . '/manual';
    }

    private function file(string $content): string
    {
        $file = sys_get_temp_dir() . '/manual-redirects-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
