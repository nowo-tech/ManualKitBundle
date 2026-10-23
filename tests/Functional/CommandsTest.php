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

    public function testCheckWithoutTranslationLocales(): void
    {
        $check = $this->tester('nowo:manual:check', ['locales' => ['en']]);

        self::assertSame(Command::SUCCESS, $check->execute([]));
        self::assertStringContainsString('No translation locale is configured', $check->getDisplay());
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
