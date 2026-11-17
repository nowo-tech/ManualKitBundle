<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Redirect;

use Nowo\ManualKitBundle\Redirect\RedirectMapLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * 1.11: the manifest's `redirects:` entry — a YAML file of the manual directory or an inline mapping — merged with the
 * configuration map.
 */
final class RedirectMapLoaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/manual-redirects-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/manual', 0o777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testWithoutAManifestOnlyTheConfigurationMapIsUsed(): void
    {
        self::assertSame(['rules' => ['a' => 'b'], 'files' => [], 'errors' => []], RedirectMapLoader::load(['a' => 'b'], $this->directory . '/manual'));
    }

    public function testAnUnreadableManifestOrOneWithoutRedirectsAddsNothing(): void
    {
        $this->write('manifest.yaml', "chapters: [\n");
        $loaded = RedirectMapLoader::load(['a' => 'b'], $this->manual());
        self::assertSame([['a' => 'b'], []], [$loaded['rules'], $loaded['errors']]);
        self::assertSame([$this->manual() . '/manifest.yaml'], $loaded['files']);

        $this->write('manifest.yaml', "chapters: []\n");
        self::assertSame(['a' => 'b'], RedirectMapLoader::load(['a' => 'b'], $this->manual())['rules']);
    }

    public function testAFileNamedByTheManifestIsMerged(): void
    {
        $this->write('manifest.yaml', "redirects: redirects.yaml\nchapters: []\n");
        $this->write('redirects.yaml', "redirects:\n  old: new\n  'old#x': 'new#y'\n");

        $loaded = RedirectMapLoader::load(['a' => 'b', 'old' => 'new'], $this->manual());
        self::assertSame(['a' => 'b', 'old' => 'new', 'old#x' => 'new#y'], $loaded['rules']);
        self::assertSame([$this->manual() . '/manifest.yaml', $this->realManual() . '/redirects.yaml'], $loaded['files']);
        self::assertSame([], $loaded['errors']);

        $this->write('redirects.yaml', "old: new\n");
        self::assertSame(['old' => 'new'], RedirectMapLoader::load([], $this->manual())['rules'], 'the map may sit at the top level');

        $this->write('redirects.yaml', '');
        self::assertSame(['rules' => [], 'errors' => []], $this->rulesAndErrors(RedirectMapLoader::load([], $this->manual())), 'an empty file is an empty map');
    }

    public function testAnInlineMappingInTheManifestIsMerged(): void
    {
        $this->write('manifest.yaml', "redirects:\n  old: new\nchapters: []\n");

        self::assertSame(['old' => 'new'], RedirectMapLoader::load([], $this->manual())['rules']);
    }

    public function testConflictsAndBadEntriesAreReported(): void
    {
        $this->write('manifest.yaml', "redirects:\n  old: new\n  other: [x]\nchapters: []\n");
        $loaded = RedirectMapLoader::load(['old' => 'elsewhere', 'other' => 'y'], $this->manual());
        self::assertStringContainsString('Redirect "old" is mapped twice: "elsewhere" in nowo_manual_kit.redirects and "new" in the manifest redirects.', implode(' ', $loaded['errors']));
        self::assertStringContainsString('Redirect "other" is mapped twice: "y" in nowo_manual_kit.redirects and "array"', implode(' ', $loaded['errors']));

        $loaded = RedirectMapLoader::load(['old' => ['bad']], $this->manual());
        self::assertStringContainsString('"array" in nowo_manual_kit.redirects', implode(' ', $loaded['errors']));

        $this->write('manifest.yaml', "redirects: 3\nchapters: []\n");
        self::assertStringContainsString('"redirects" must be the name of a YAML file of the manual directory or a mapping of old → new paths, got int.', implode(' ', RedirectMapLoader::load([], $this->manual())['errors']));
    }

    public function testTheFileMustBeAYamlFileInsideTheManual(): void
    {
        $this->write('../outside.yaml', "old: new\n");
        $this->write('redirects.txt', "old: new\n");
        foreach (['missing.yaml', '../outside.yaml', 'redirects.txt'] as $name) {
            $this->write('manifest.yaml', "redirects: '" . $name . "'\nchapters: []\n");
            $loaded = RedirectMapLoader::load(['a' => 'b'], $this->manual());
            self::assertSame(['a' => 'b'], $loaded['rules'], $name);
            self::assertStringContainsString('the redirects file "' . $name . '" must be an existing .yaml file inside', implode(' ', $loaded['errors']), $name);
        }

        $this->write('manifest.yaml', "redirects: redirects.yml\nchapters: []\n");
        $this->write('redirects.yml', "old: [\n");
        $loaded = RedirectMapLoader::load([], $this->manual());
        self::assertStringContainsString('The redirects file redirects.yml cannot be parsed', implode(' ', $loaded['errors']));
        self::assertCount(2, $loaded['files'], 'the broken file is still tracked');

        $this->write('redirects.yml', "just a string\n");
        self::assertStringContainsString('got string', implode(' ', RedirectMapLoader::load([], $this->manual())['errors']));
    }

    private function manual(): string
    {
        return $this->directory . '/manual';
    }

    private function realManual(): string
    {
        return (string) realpath($this->manual());
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->manual() . '/' . $name, $content);
    }

    /**
     * @param array{rules: array<mixed>, files: list<string>, errors: list<string>} $loaded
     *
     * @return array{rules: array<mixed>, errors: list<string>}
     */
    private function rulesAndErrors(array $loaded): array
    {
        return ['rules' => $loaded['rules'], 'errors' => $loaded['errors']];
    }
}
