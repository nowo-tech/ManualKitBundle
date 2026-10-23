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

final class CommandsTest extends KernelTestCase
{
    private const array LIBRE = ['machine_translation' => ['provider' => 'libretranslate', 'url' => 'http://libretranslate.test']];

    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/manual-commands-' . bin2hex(random_bytes(4));
        new Filesystem()->mirror(__DIR__ . '/../Fixtures/manual', $this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        // The directory is part of the kernel cache key, so every test compiles its own container.
        return new TestKernel($options['manual'] ?? []);
    }

    public function testCheckReportsAndStrictFails(): void
    {
        $check = $this->tester('nowo:manual:check');

        self::assertSame(Command::SUCCESS, $check->execute([]));
        $output = $check->getDisplay();
        self::assertMatchesRegularExpression('/es\s+01-using\s+outdated\s+filters/', $output);
        self::assertMatchesRegularExpression('/es\s+02-admin\s+missing/', $output);
        self::assertMatchesRegularExpression('/es\s+README\s+translated/', $output);
        self::assertStringContainsString('1 translation(s) outdated or missing anchors', $output);
        self::assertSame(Command::FAILURE, $check->execute(['--strict' => true]));
    }

    public function testStampThenTheCheckPasses(): void
    {
        $stamp = $this->tester('nowo:manual:stamp');
        self::assertSame(Command::FAILURE, $stamp->execute(['locale' => 'es', 'chapter' => '02-admin']));
        self::assertStringContainsString('No translation es/02-admin.md', $stamp->getDisplay());

        file_put_contents($this->directory . '/es/01-using.md', "# Usar\n\n<a id=\"filters\"></a>\n\n## Filtrar\n");
        self::assertSame(Command::SUCCESS, $stamp->execute(['locale' => 'es', 'chapter' => '01-using']));
        self::assertStringContainsString('es/01-using.md marked as up to date', $stamp->getDisplay());

        $check = $this->tester('nowo:manual:check');
        self::assertSame(Command::SUCCESS, $check->execute(['--strict' => true]));
        self::assertStringContainsString('3 chapter translations checked', $check->getDisplay());
    }

    public function testManifestProblemsAreReportedAndFailStrictAndSources(): void
    {
        file_put_contents($this->directory . '/manifest.yaml', (string) file_get_contents($this->directory . '/manifest.yaml') . "  - { slug: 09-gone }\n");
        $check = $this->tester('nowo:manual:check');

        self::assertSame(Command::SUCCESS, $check->execute([]), 'a plain check only reports');
        self::assertStringContainsString('"09-gone", which has no Markdown file', $check->getDisplay());
        self::assertSame(Command::FAILURE, $check->execute(['--sources' => true]));
        self::assertSame(Command::FAILURE, $check->execute(['--strict' => true]));

        $only = $this->tester('nowo:manual:check', ['locales' => ['en']]);
        self::assertSame(Command::FAILURE, $only->execute(['--strict' => true]), 'no translation locale, still a manifest problem');
    }

    public function testSourcesPassWhenEveryFileExists(): void
    {
        file_put_contents($this->directory . '/es/01-using.md', "# Usar\n\n<a id=\"filters\"></a>\n\n## Filtrar\n");
        $stamp = $this->tester('nowo:manual:stamp');
        $stamp->execute(['locale' => 'es', 'chapter' => '01-using']);
        $check = $this->tester('nowo:manual:check');

        self::assertSame(Command::SUCCESS, $check->execute(['--sources' => true]));
        self::assertStringContainsString('3 manual chapters, every source found', $check->getDisplay());
        self::assertSame(Command::SUCCESS, $check->execute(['--strict' => true]));
    }

    public function testCheckWithoutTranslationLocales(): void
    {
        $check = $this->tester('nowo:manual:check', ['locales' => ['en']]);

        self::assertSame(Command::SUCCESS, $check->execute([]));
        self::assertStringContainsString('No translation locale is configured', $check->getDisplay());
    }

    public function testTranslateNeedsAProviderAndATranslationLocale(): void
    {
        $translate = $this->tester('nowo:manual:translate');
        self::assertSame(Command::FAILURE, $translate->execute(['locale' => 'es']));
        self::assertStringContainsString('No machine translation provider', $translate->getDisplay());

        $translate = $this->tester('nowo:manual:translate', self::LIBRE);
        self::assertSame(Command::FAILURE, $translate->execute(['locale' => 'en']));
        self::assertStringContainsString('"en" is not a translation locale', $translate->getDisplay());
    }

    public function testTranslateWritesMissingChaptersAndKeepsHandEditedOnes(): void
    {
        $translate = $this->tester('nowo:manual:translate', self::LIBRE);

        self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es', '--dry-run' => true]));
        self::assertMatchesRegularExpression('/01-using\s+outdated\s+skipped: edited by a person/', $translate->getDisplay());
        self::assertMatchesRegularExpression('/02-admin\s+missing\s+would translate \(\d+ characters\)/', $translate->getDisplay());
        self::assertFileDoesNotExist($this->directory . '/es/02-admin.md');

        self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es']));
        self::assertStringContainsString('1 chapter(s) machine-translated into es', $translate->getDisplay());
        $written = (string) file_get_contents($this->directory . '/es/02-admin.md');
        self::assertMatchesRegularExpression('/\A<!-- translation-of: [a-f0-9]{12} -->\n<!-- machine-translated -->\n# \[es\] /', $written);

        // Up to date now; a second run has nothing to do.
        self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es', '--chapter' => ['02-admin']]));
        self::assertStringContainsString('Every es chapter is up to date', $translate->getDisplay());

        // --force replaces the hand-edited translation too.
        self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es', '--force' => true, '--chapter' => ['01-using', '02-admin']]));
        self::assertStringContainsString('2 chapter(s) machine-translated into es', $translate->getDisplay());
        self::assertStringContainsString('<!-- machine-translated -->', (string) file_get_contents($this->directory . '/es/01-using.md'));
    }

    public function testTranslateCreatesTheLocaleFolder(): void
    {
        new Filesystem()->remove($this->directory . '/es');
        $translate = $this->tester('nowo:manual:translate', self::LIBRE);

        self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es', '--chapter' => ['README']]));
        self::assertFileExists($this->directory . '/es/README.md');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function tester(string $name, array $config = []): CommandTester
    {
        self::ensureKernelShutdown();
        $application = new Application(self::bootKernel(['manual' => ['directory' => $this->directory] + $config]));

        return new CommandTester($application->find($name));
    }
}
