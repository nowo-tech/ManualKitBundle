<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function bin2hex;
use function file_put_contents;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

/**
 * 1.11: `nowo:manual:redirects` — missing targets, cycles, chains, rules that never apply and old slugs in link maps.
 */
final class RedirectsCommandTest extends KernelTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['manual'] ?? ChapterRedirectsTest::CONFIG);
    }

    public function testTheDeployedMapWarnsAboutItsChainAndStrictFails(): void
    {
        $command = $this->tester();

        self::assertSame(Command::SUCCESS, $command->execute([]));
        $output = $command->getDisplay();
        self::assertMatchesRegularExpression('/warning\s+05-chain → 01-install\s+Chain: "01-install" is redirected again; served in one hop to "setup"/', $output);
        self::assertStringContainsString('0 error(s), 1 warning(s) in 13 redirect rule(s).', $output);
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
        self::assertStringNotContainsString('setup#overview', $output, 'a current link is fine');
        self::assertStringNotContainsString('app_nested', $output);
        self::assertStringContainsString('0 error(s), 4 warning(s)', $output);
    }

    public function testACleanMapPasses(): void
    {
        $command = $this->tester();

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

    public function testWithoutAMapThereIsNothingToReport(): void
    {
        self::bootKernel(['manual' => []]);
        $command = new CommandTester(new Application(self::$kernel ?? self::fail('kernel'))->find('nowo:manual:redirects'));

        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertStringContainsString('0 redirect rule(s) checked', $command->getDisplay());
    }

    private function tester(): CommandTester
    {
        self::bootKernel();

        return new CommandTester(new Application(self::$kernel ?? self::fail('kernel'))->find('nowo:manual:redirects'));
    }

    private function file(string $content): string
    {
        $file = sys_get_temp_dir() . '/manual-redirects-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
