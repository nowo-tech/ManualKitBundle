<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Catalog;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\TranslationState;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_map;
use function array_reverse;
use function array_unique;
use function basename;
use function count;
use function dirname;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function json_encode;
use function max;
use function min;
use function realpath;
use function rtrim;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const JSON_UNESCAPED_SLASHES;

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

    /** A chapter slug (also a URL segment and a file name). */
    public const string SLUG = '/^[A-Za-z0-9-]+$/';

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

    /**
     * Returns the manual directory.
     */
    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Returns the locale the source chapters are written in.
     */
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
        return $this->scan($locale)[0];
    }

    /**
     * 1.3: what the manifest asks for but cannot be served — a missing file, a pattern that matches nothing, a path
     * outside the docs root, a bad or repeated slug. The chapter is left out instead of failing the page;
     * `nowo:manual:check --strict` (or `--sources`) fails on these.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->scan(null)[1];
    }

    /**
     * @return array{0: list<ManualChapter>, 1: list<string>}
     */
    private function scan(?string $locale): array
    {
        $manifestPath = $this->directory . '/' . self::MANIFEST;
        if (!is_file($manifestPath)) {
            return [[], [sprintf('%s not found in %s.', self::MANIFEST, $this->directory)]];
        }
        try {
            $manifest = Yaml::parseFile($manifestPath);
        } catch (ParseException $e) {
            return [[], [sprintf('%s cannot be parsed: %s', self::MANIFEST, $e->getMessage())]];
        }
        $rows = is_array($manifest) && is_array($manifest['chapters'] ?? null) ? $manifest['chapters'] : [];

        /** @var array<string, array{row: array<mixed>, external: string|null, parent: string|null, audience: mixed}> $entries */
        $entries  = [];
        $problems = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $problems[] = sprintf('%s: chapter #%s is not a mapping.', self::MANIFEST, (string) $index);

                continue;
            }
            // 1.6: `parent` puts the row's chapters under another chapter (their files may sit in its sub-folder).
            $parent = $row['parent'] ?? null;
            if ($parent !== null && (!is_string($parent) || preg_match(self::SLUG, $parent) !== 1)) {
                $problems[] = sprintf('%s: the parent %s is not a chapter slug; the row is left out.', self::MANIFEST, json_encode($parent, JSON_UNESCAPED_SLASHES));

                continue;
            }
            foreach ($this->sources($row, $problems, $parent !== null) as [$slug, $external]) {
                if (isset($entries[$slug])) {
                    $problems[] = sprintf('%s: the slug "%s" is listed twice; the second one is left out.', self::MANIFEST, $slug);

                    continue;
                }
                // audience is "member" (default) or "admin"; anything else (a typo) is treated as admin — fail closed.
                $audience = $row['audience'] ?? 'member';
                if ($audience !== 'member' && $audience !== 'admin') {
                    $problems[] = sprintf('%s: chapter "%s" has an unknown audience %s (use member or admin); it is shown to admins only.', self::MANIFEST, $slug, json_encode($audience));
                }
                $entries[$slug] = ['row' => $row, 'external' => $external, 'parent' => $parent, 'audience' => $audience];
            }
        }

        // 1.6: the tree — every ancestor known, no cycle, at most MAX_DEPTH levels, a file for every nested chapter.
        /** @var array<string, false|list<string>> $ancestry */
        $ancestry = [];
        foreach ($entries as $slug => $entry) {
            $this->ancestry($slug, $entries, $ancestry, $problems);
        }
        $nodes = [];
        foreach ($entries as $slug => $entry) {
            $ancestors = $ancestry[$slug];
            if ($ancestors === false) {
                continue;
            }
            $folder = '';
            if ($entry['external'] === null && $ancestors !== []) {
                $folder = $this->nestedFolder($slug, $ancestors);
                if ($folder === null) {
                    $problems[]      = sprintf('%s lists "%s", which has no Markdown file (looked in %s/ and at the manual root).', self::MANIFEST, $slug, implode('/', $ancestors));
                    $ancestry[$slug] = false;

                    continue;
                }
            }
            $nodes[$slug] = [$entry, $ancestors, $folder];
        }

        $built = [];
        foreach ($nodes as $slug => [$entry, $ancestors, $folder]) {
            // A chapter whose ancestor was left out (no file) goes with it.
            foreach ($ancestors as $ancestor) {
                if (!isset($nodes[$ancestor])) {
                    continue 2;
                }
            }
            $row                     = $entry['row'];
            $external                = $entry['external'];
            [$path, $served, $state] = $external === null ? $this->resolve($slug, $locale, $folder) : [$external, $this->sourceLocale, TranslationState::Source];
            $top                     = $ancestors === [] ? $row : $nodes[$ancestors[0]][0]['row'];
            // A child inherits its parent's audience and group and may only narrow the audience.
            $adminOnly = false;
            foreach ([...$ancestors, $slug] as $own) {
                $adminOnly = $adminOnly || $nodes[$own][0]['audience'] !== 'member';
            }
            if ($ancestors !== [] && is_string($row['group'] ?? null) && $row['group'] !== ($top['group'] ?? 'using')) {
                $problems[] = sprintf('%s: chapter "%s" sits under "%s" and takes its group; its own group "%s" is ignored.', self::MANIFEST, $slug, $ancestors[0], $row['group']);
            }
            $built[$slug] = new ManualChapter(
                $slug,
                is_string($top['group'] ?? null) ? $top['group'] : 'using',
                $adminOnly,
                is_string($row['icon'] ?? null) ? $row['icon'] : 'tabler:book',
                $this->title($path, $slug),
                $path,
                is_array($row['tags'] ?? null) ? array_values(array_filter(array_map(strval(...), $row['tags']), static fn (string $tag): bool => trim($tag) !== '')) : [],
                $served,
                $state,
                $external !== null,
                $external === null ? $this->manualDir($folder) : $this->docsDir($external),
                is_int($row['toc_depth'] ?? null) ? max(2, min(4, $row['toc_depth'])) : 4,
                $ancestors,
                [],
                $folder,
            );
        }

        return [$this->depthFirst($built), $problems];
    }

    /**
     * The slugs above `$slug`, top-level first, or false (with a problem) for an unknown parent, a cycle, a tree
     * deeper than {@see ManualChapter::MAX_DEPTH} or the home page in the tree.
     *
     * @param array<string, array{row: array<mixed>, external: string|null, parent: string|null, audience: mixed}> $entries
     * @param array<string, false|list<string>> $ancestry memo, filled in
     * @param list<string> $problems
     *
     * @return false|list<string>
     */
    private function ancestry(string $slug, array $entries, array &$ancestry, array &$problems): array|false
    {
        $chain  = [];
        $cursor = $entries[$slug]['parent'];
        while ($cursor !== null) {
            if ($cursor === $slug || in_array($cursor, $chain, true)) {
                $problems[] = sprintf('%s: the parents of "%s" form a cycle; it is left out.', self::MANIFEST, $slug);

                return $ancestry[$slug] = false;
            }
            if (!isset($entries[$cursor])) {
                $problems[] = sprintf('%s: chapter "%s" has an unknown parent "%s"; it is left out.', self::MANIFEST, $slug, $cursor);

                return $ancestry[$slug] = false;
            }
            $chain[] = $cursor;
            if (count($chain) >= ManualChapter::MAX_DEPTH) {
                $problems[] = sprintf('%s: chapter "%s" is nested deeper than %d levels; it is left out.', self::MANIFEST, $slug, ManualChapter::MAX_DEPTH);

                return $ancestry[$slug] = false;
            }
            $cursor = $entries[$cursor]['parent'];
        }
        if ($chain !== [] && ($slug === ManualChapter::HOME || in_array(ManualChapter::HOME, $chain, true))) {
            $problems[] = sprintf('%s: the home page (%s) cannot have a parent or children; "%s" is left out.', self::MANIFEST, ManualChapter::HOME, $slug);

            return $ancestry[$slug] = false;
        }

        // The walk above meets every reason an ancestor could be left out (unknown, cycle, depth, home page).
        return $ancestry[$slug] = array_reverse($chain);
    }

    /**
     * 1.6: where a nested chapter's file lives — `{directory}/{ancestors…}/{slug}.md` when it exists, else the manual
     * root — or null. Slugs are `[A-Za-z0-9-]+`, so the folder cannot climb out of the manual; a symbolic link that
     * points outside it is refused too.
     *
     * @param list<string> $ancestors
     */
    private function nestedFolder(string $slug, array $ancestors): ?string
    {
        $folder = implode('/', $ancestors);
        foreach ([$folder, ''] as $candidate) {
            $file = $this->sourcePath($slug, $candidate);
            if (is_file($file) && $this->insideManual($file)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Whether a file resolves (links followed) inside the manual directory. */
    private function insideManual(string $file): bool
    {
        $root = realpath($this->directory);
        $path = realpath($file);

        return $root !== false && $path !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /** The docs-root folder of a manual file: `manual`, or `manual/guide` for a file of a sub-folder. */
    private function manualDir(string $folder): string
    {
        return basename($this->directory) . ($folder === '' ? '' : '/' . $folder);
    }

    /**
     * Every chapter followed by its descendants (manifest order among siblings), the titles of the ancestors filled in.
     *
     * @param array<string, ManualChapter> $built
     *
     * @return list<ManualChapter>
     */
    private function depthFirst(array $built): array
    {
        /** @var array<string, list<ManualChapter>> $children */
        $children = [];
        foreach ($built as $chapter) {
            $children[$chapter->parent() ?? ''][] = $chapter;
        }
        $ordered = [];
        $walk    = static function (string $parent, array $titles) use (&$walk, &$ordered, $children): void {
            $titles = array_values(array_map(strval(...), $titles));
            foreach ($children[$parent] ?? [] as $chapter) {
                $ordered[] = $titles === [] ? $chapter : new ManualChapter(
                    $chapter->slug,
                    $chapter->group,
                    $chapter->adminOnly,
                    $chapter->icon,
                    $chapter->title,
                    $chapter->path,
                    $chapter->tags,
                    $chapter->locale,
                    $chapter->translation,
                    $chapter->external,
                    $chapter->docsDir,
                    $chapter->tocDepth,
                    $chapter->ancestors,
                    $titles,
                    $chapter->folder,
                );
                $walk($chapter->slug, [...$titles, $chapter->title]);
            }
        };
        $walk('', []);

        return $ordered;
    }

    /**
     * The chapters one manifest row stands for: `{slug}` (the manual's own file), `{slug, file}` (1.2: one file of the
     * docs root) or `{include}` (1.3: a glob or a folder of the docs root, one chapter per Markdown file, slug from the
     * file name).
     *
     * @param array<mixed> $row
     * @param list<string> $problems
     * @param bool $nested 1.6: the row has a `parent`, so its file is looked for once the tree is known
     *
     * @return list<array{0: string, 1: string|null}> slug, external path (null = `{directory}/{slug}.md`)
     */
    private function sources(array $row, array &$problems, bool $nested = false): array
    {
        if (is_string($row['include'] ?? null)) {
            $files = $this->included($row['include'], $problems);

            return array_map(static fn (string $file): array => [self::slugFor($file), $file], $files);
        }
        $slug = $row['slug'] ?? null;
        if (!is_string($slug) || preg_match(self::SLUG, $slug) !== 1) {
            $problems[] = sprintf('%s: a chapter needs a slug made of letters, digits and dashes (or an include).', self::MANIFEST);

            return [];
        }
        if (is_string($row['file'] ?? null)) {
            $file = $this->externalFile($row['file']);
            if ($file === null) {
                $problems[] = sprintf('%s: the file of "%s" (%s) must be an existing Markdown file inside %s.', self::MANIFEST, $slug, $row['file'], dirname($this->directory));

                return [];
            }

            return [[$slug, $file]];
        }
        if (!$nested && !is_file($this->sourcePath($slug))) {
            $problems[] = sprintf('%s lists "%s", which has no Markdown file.', self::MANIFEST, $slug);

            return [];
        }

        return [[$slug, null]];
    }

    /**
     * @param list<string> $problems
     *
     * @return list<string> real paths, sorted
     */
    private function included(string $pattern, array &$problems): array
    {
        $base  = $this->directory . '/' . $pattern;
        $paths = is_dir($base) ? (glob(rtrim($base, '/') . '/*.md') ?: []) : (glob($base) ?: []);
        $files = [];
        foreach ($paths as $path) {
            $file = $this->externalFile(substr($path, strlen($this->directory) + 1));
            if ($file !== null) {
                $files[] = $file;
            }
        }
        sort($files);
        if ($files === []) {
            $problems[] = sprintf('%s: the include "%s" matches no Markdown file inside %s.', self::MANIFEST, $pattern, dirname($this->directory));
        }

        return array_values(array_unique($files));
    }

    /** `docs/adr/0001 Use MySQL.md` → `0001-Use-MySQL`. */
    private static function slugFor(string $file): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', basename($file, '.md')), '-');
    }

    /**
     * @return list<ManualChapter>
     */
    public function visible(bool $admin, ?string $locale = null): array
    {
        return array_values(array_filter($this->all($locale), static fn (ManualChapter $chapter): bool => $admin || !$chapter->adminOnly));
    }

    /**
     * Finds a chapter by slug in the given locale, or null.
     */
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

    /**
     * 1.6: the chapters right under `$chapter` that the viewer may read, in manual order.
     *
     * @return list<ManualChapter>
     */
    public function children(ManualChapter $chapter, bool $admin, ?string $locale = null): array
    {
        return array_values(array_filter($this->visible($admin, $locale), static fn (ManualChapter $candidate): bool => $candidate->parent() === $chapter->slug));
    }

    /**
     * 1.6: the chapters above `$chapter`, top-level first (all readable whenever `$chapter` is: a hidden parent hides
     * its children).
     *
     * @return list<ManualChapter>
     */
    public function ancestors(ManualChapter $chapter, ?string $locale = null): array
    {
        $found = [];
        foreach ($chapter->ancestors as $slug) {
            $ancestor = $this->find($slug, $locale);
            if ($ancestor instanceof ManualChapter) {
                $found[] = $ancestor;
            }
        }

        return $found;
    }

    /**
     * Returns the path of a source-language chapter file (1.6: `$folder` = its sub-folder of the manual).
     */
    public function sourcePath(string $slug, string $folder = ''): string
    {
        return $this->directory . '/' . ($folder === '' ? '' : $folder . '/') . $slug . '.md';
    }

    /**
     * Returns the path of a chapter translation file (1.6: in the same sub-folder as its source).
     */
    public function translationPath(string $slug, string $locale, string $folder = ''): string
    {
        return $this->directory . '/' . $locale . '/' . ($folder === '' ? '' : $folder . '/') . $slug . '.md';
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
    private function resolve(string $slug, ?string $locale, string $folder = ''): array
    {
        $source = $this->sourcePath($slug, $folder);
        if ($locale === null || !$this->translates($locale)) {
            return [$source, $this->sourceLocale, TranslationState::Source];
        }
        $translation = $this->translationPath($slug, $locale, $folder);
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
     * docs root — the folder that holds the manual — so the manifest cannot publish anything else. Null otherwise.
     */
    private function externalFile(string $file): ?string
    {
        $root = realpath(dirname($this->directory));
        $path = realpath($this->directory . '/' . $file);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !str_ends_with($path, '.md') || !is_file($path)) {
            return null;
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
