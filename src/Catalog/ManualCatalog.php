<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Catalog;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\TranslationState;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function basename;
use function dirname;
use function in_array;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function max;
use function min;
use function realpath;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * The chapters of `{directory}/manifest.yaml` in order, with their audience, resolved for a locale.
 *
 * Only listed chapters are ever served (no path from the request reaches the filesystem). The root files are the
 * manual's source language; a translation lives in `{directory}/{locale}/{slug}.md` and carries
 * `<!-- translation-of: <hash> -->` — the {@see self::sourceHash()} of the source it was written from, so a changed
 * source marks it {@see TranslationState::Outdated}. A chapter without a translation is served from the source.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualCatalog
{
    public const string MANIFEST = 'manifest.yaml';

    /** `<!-- translation-of: 1a2b3c4d5e6f -->` in a translated file. */
    public const string TRANSLATION_MARKER = '/<!--\s*translation-of:\s*([a-f0-9]{12})\s*-->/';

    /**
     * @param list<string> $locales the locales a translation may exist for (the source locale included or not)
     */
    public function __construct(
        private string $directory,
        private string $sourceLocale = 'en',
        private array $locales = [],
    ) {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function sourceLocale(): string
    {
        return $this->sourceLocale;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return $this->locales;
    }

    /**
     * @return list<ManualChapter>
     */
    public function all(?string $locale = null): array
    {
        $manifest = Yaml::parseFile($this->directory . '/' . self::MANIFEST);
        $rows     = is_array($manifest) && is_array($manifest['chapters'] ?? null) ? $manifest['chapters'] : [];

        $chapters = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['slug'] ?? null) || preg_match('/^[A-Za-z0-9-]+$/', $row['slug']) !== 1) {
                throw new RuntimeException(sprintf('%s: every chapter needs a slug made of letters, digits and dashes.', self::MANIFEST));
            }
            $external = is_string($row['file'] ?? null) ? $this->externalFile($row['slug'], $row['file']) : null;
            if ($external === null && !is_file($this->sourcePath($row['slug']))) {
                throw new RuntimeException(sprintf('%s lists "%s", which has no Markdown file.', self::MANIFEST, $row['slug']));
            }
            [$path, $served, $state] = $external === null ? $this->resolve($row['slug'], $locale) : [$external, $this->sourceLocale, TranslationState::Source];
            $chapters[]              = new ManualChapter(
                $row['slug'],
                is_string($row['group'] ?? null) ? $row['group'] : 'using',
                'admin' === ($row['audience'] ?? 'member'),
                is_string($row['icon'] ?? null) ? $row['icon'] : 'tabler:book',
                $this->title($path, $row['slug']),
                $path,
                is_array($row['tags'] ?? null) ? array_values(array_filter(array_map(strval(...), $row['tags']), static fn (string $tag): bool => trim($tag) !== '')) : [],
                $served,
                $state,
                $external !== null,
                $external === null ? basename($this->directory) : $this->docsDir($external),
                is_int($row['toc_depth'] ?? null) ? max(2, min(4, $row['toc_depth'])) : 4,
            );
        }

        return $chapters;
    }

    /**
     * @return list<ManualChapter>
     */
    public function visible(bool $admin, ?string $locale = null): array
    {
        return array_values(array_filter($this->all($locale), static fn (ManualChapter $chapter): bool => $admin || !$chapter->adminOnly));
    }

    public function find(string $slug, ?string $locale = null): ?ManualChapter
    {
        foreach ($this->all($locale) as $chapter) {
            if ($chapter->slug === $slug) {
                return $chapter;
            }
        }

        return null;
    }

    /**
     * The chapters before and after `$chapter` among those the viewer may read.
     *
     * @return array{0: ManualChapter|null, 1: ManualChapter|null}
     */
    public function neighbours(ManualChapter $chapter, bool $admin, ?string $locale = null): array
    {
        $visible = $this->visible($admin, $locale);
        foreach ($visible as $index => $candidate) {
            if ($candidate->slug === $chapter->slug) {
                return [$visible[$index - 1] ?? null, $visible[$index + 1] ?? null];
            }
        }

        return [null, null];
    }

    public function sourcePath(string $slug): string
    {
        return $this->directory . '/' . $slug . '.md';
    }

    public function translationPath(string $slug, string $locale): string
    {
        return $this->directory . '/' . $locale . '/' . $slug . '.md';
    }

    /** First 12 hex digits of the SHA-256 of a source file's content (what a translation records). */
    public static function sourceHash(string $markdown): string
    {
        return substr(hash('sha256', $markdown), 0, 12);
    }

    /** Whether `$locale` reads translations (a configured locale other than the source one). */
    public function translates(?string $locale): bool
    {
        return $locale !== null && $locale !== $this->sourceLocale && in_array($locale, $this->locales, true);
    }

    /**
     * @return array{0: string, 1: string, 2: TranslationState} path, locale of that file, state
     */
    private function resolve(string $slug, ?string $locale): array
    {
        $source = $this->sourcePath($slug);
        if ($locale === null || !$this->translates($locale)) {
            return [$source, $this->sourceLocale, TranslationState::Source];
        }
        $translation = $this->translationPath($slug, $locale);
        if (!is_file($translation)) {
            return [$source, $this->sourceLocale, TranslationState::Missing];
        }
        $marker = preg_match(self::TRANSLATION_MARKER, (string) file_get_contents($translation), $match) ? $match[1] : null;

        return [
            $translation,
            $locale,
            $marker === self::sourceHash((string) file_get_contents($source)) ? TranslationState::Translated : TranslationState::Outdated,
        ];
    }

    /**
     * 1.2: a manifest `file` (relative to the manual folder, e.g. `../CHANGELOG.md`) must be a Markdown file inside the
     * docs root — the folder that holds the manual — so the manifest cannot publish anything else.
     */
    private function externalFile(string $slug, string $file): string
    {
        $root = realpath(dirname($this->directory));
        $path = realpath($this->directory . '/' . $file);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !str_ends_with($path, '.md') || !is_file($path)) {
            throw new RuntimeException(sprintf('%s: the file of "%s" must be a Markdown file inside %s.', self::MANIFEST, $slug, dirname($this->directory)));
        }

        return $path;
    }

    /** The folder of `$path` relative to the docs root (`` for a file at the root). */
    private function docsDir(string $path): string
    {
        $root = (string) realpath(dirname($this->directory));

        return trim(substr(dirname($path), strlen($root)), DIRECTORY_SEPARATOR);
    }

    private function title(string $path, string $fallback): string
    {
        $handle = fopen($path, 'r');
        $title  = $fallback;
        while ($handle !== false && false !== ($line = fgets($handle))) {
            // The heading may follow the translation marker.
            if (str_starts_with($line, '# ')) {
                $title = trim(substr($line, 2));

                break;
            }
            if (trim($line) !== '' && !str_starts_with(trim($line), '<!--')) {
                break;
            }
        }
        if ($handle !== false) {
            fclose($handle);
        }

        return $title;
    }
}
