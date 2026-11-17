<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Command;

use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectsInterface;
use Nowo\ManualKitBundle\Redirect\RedirectMapCompiler;
use Nowo\ManualKitBundle\Redirect\RedirectTarget;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_key_first;
use function array_pop;
use function basename;
use function count;
use function dirname;
use function explode;
use function file;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_file;
use function is_string;
use function preg_match_all;
use function rawurldecode;
use function realpath;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

use const ENT_HTML5;
use const ENT_QUOTES;
use const FILE_IGNORE_NEW_LINES;
use const PREG_SET_ORDER;

/**
 * 1.11: lints the chapter redirect map — targets that do not exist (chapter or anchor), cycles, chains (a target that
 * is redirected again), rules that never apply, and old slugs still used in the host's link maps (UiKit
 * `nowo_ui_kit.route_links` by default). Errors fail; warnings fail with `--strict`. `--file` lints a map file before it
 * is deployed (cycles and invalid targets included: a deployed map never has those, the container refuses it).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'nowo:manual:redirects', description: 'Check the manual redirect map: missing targets, cycles, chains and old slugs still linked.')]
final readonly class RedirectsCommand
{
    /** A manual document link in a link map: `manual/old-slug.md#anchor` (up to two folders down). */
    /** `[text](target "title")` / `![alt](target)`: the target (images are filtered out by {@see self::CHAPTER_LINK}). */
    private const string MARKDOWN_LINK = '/\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/';

    /** A relative link to a Markdown file, with an optional anchor (no scheme, no host). */
    private const string CHAPTER_LINK = '~^((?:\.{1,2}/)*(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9-]+\.md)(?:#([^\s#]*))?$~';

    private const string DOC_LINK = '~(?:^|/)manual/(?:[A-Za-z0-9-]+/){0,2}([A-Za-z0-9-]+)\.md(?:#([\p{L}\p{N}_-]+))?~u';

    /**
     * Receives the redirect map, the catalog and renderer that say what exists, and the container parameters that
     * hold the host's link maps.
     */
    public function __construct(
        private ChapterRedirectsInterface $redirects,
        private ManualCatalogInterface $catalog,
        private ManualRendererInterface $renderer,
        private ParameterBagInterface $parameters,
    ) {
    }

    /**
     * Lists the problems of the map; returns a failure code on an error (or a warning with `--strict`).
     *
     * @param list<string> $routeLinks
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Fail on warnings too (chains, rules that never apply, old slugs still linked)')]
        bool $strict = false,
        #[Option(description: 'Lint this YAML map file (old: new) instead of the deployed map')]
        ?string $file = null,
        #[Option(description: 'Container parameter holding a link map to scan for old slugs (repeatable)')]
        array $routeLinks = ['nowo_ui_kit.route_links'],
    ): int {
        $rows = [];
        if ($file !== null) {
            $rules = $this->readFile($file, $rows);
        } else {
            $rules = $this->redirects->rules();
        }
        $compiled = RedirectMapCompiler::compile($rules);
        foreach ($compiled->errors as $error) {
            $rows[] = ['error', '', $error];
        }
        $rules = $compiled->rules;

        $chapters = [];
        foreach ($this->catalog->all() as $chapter) {
            $chapters[$chapter->slug] = $chapter;
        }
        foreach ($rules as $from => $to) {
            $from   = (string) $from;
            $origin = RedirectTarget::fromString($from);
            $rule   = $from . ' → ' . $to;
            if ($origin->anchor === null && isset($chapters[$from])) {
                $rows[] = ['error', $rule, sprintf('"%s" is still a chapter: the redirect never applies. Remove the rule or the chapter.', $from)];

                continue;
            }
            if ($origin->anchor !== null && !isset($chapters[$origin->slug]) && !isset($rules[$origin->slug])) {
                $rows[] = ['warning', $rule, sprintf('"%s" is neither a chapter nor redirected: this anchor rule never applies.', $origin->slug)];
            }
            $final = $compiled->chains[$from] ?? $to;
            if (isset($compiled->chains[$from])) {
                $rows[] = ['warning', $rule, sprintf('Chain: "%s" is redirected again; served in one hop to "%s". Write the final target.', $to, $final)];
            }
            $walked = RedirectMapCompiler::resolvePosition($from, $rules, static fn (string $slug): bool => isset($chapters[$slug]));
            if ($walked !== null && $walked !== $final) {
                // The server stops at an existing chapter: the reader lands there, not on the collapsed target.
                $rows[] = ['warning', $rule, sprintf('The chain passes through the existing chapter "%s": served to "%s", not "%s".', RedirectTarget::fromString($walked)->slug, $walked, $final)];
                $final  = $walked;
            }
            $problem = $this->missing(RedirectTarget::fromString($final), $chapters);
            if ($problem !== null) {
                $rows[] = ['error', $rule, $problem];
            }
        }
        foreach ($routeLinks as $parameter) {
            foreach ($this->oldLinks($parameter, $rules, $chapters) as $row) {
                $rows[] = $row;
            }
        }
        foreach ($this->chapterLinks($rules) as $row) {
            $rows[] = $row;
        }

        $errors   = count(array_filter($rows, static fn (array $row): bool => $row[0] === 'error'));
        $warnings = count($rows) - $errors;
        if ($rows === []) {
            $io->success(sprintf('%d redirect rule(s) checked: every target exists, no chain, no old slug linked; every chapter link resolves.', count($rules)));

            return Command::SUCCESS;
        }
        $io->table(['Level', 'Rule / file:line', 'Problem'], $rows);
        $io->warning(sprintf('%d error(s), %d warning(s) in %d redirect rule(s).', $errors, $warnings, count($rules)));

        return $errors > 0 || ($strict && $warnings > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     *
     * @return array<mixed>
     */
    private function readFile(string $file, array &$rows): array
    {
        if (!is_file($file)) {
            $rows[] = ['error', '', sprintf('File %s not found.', $file)];

            return [];
        }
        try {
            $content = Yaml::parseFile($file);
        } catch (ParseException $e) {
            $rows[] = ['error', '', sprintf('%s cannot be parsed: %s', $file, $e->getMessage())];

            return [];
        }
        if (is_array($content) && count($content) === 1 && array_key_first($content) === 'redirects') {
            $content = $content['redirects'];
        }
        if (!is_array($content)) {
            $rows[] = ['error', '', sprintf('%s must hold a mapping of old → new paths.', $file)];

            return [];
        }

        return $content;
    }

    /**
     * Why a final target cannot be served (unknown chapter, anchor not in the chapter), or null.
     *
     * @param array<string, ManualChapter> $chapters
     */
    private function missing(RedirectTarget $target, array $chapters): ?string
    {
        $chapter = $chapters[$target->slug] ?? null;
        if (!$chapter instanceof ManualChapter) {
            return sprintf('Target chapter "%s" does not exist.', $target->slug);
        }
        if ($target->anchor !== null && !str_contains($this->renderer->render($chapter, true)->html, ' id="' . htmlspecialchars($target->anchor, ENT_QUOTES | ENT_HTML5) . '"')) {
            return sprintf('Target anchor "%s" is not in chapter "%s".', $target->anchor, $target->slug);
        }

        return null;
    }

    /**
     * The manual links of a link-map parameter that still point at an old slug or anchor.
     *
     * @param array<string, string> $rules
     * @param array<string, ManualChapter> $chapters
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function oldLinks(string $parameter, array $rules, array $chapters): array
    {
        if (!$this->parameters->has($parameter)) {
            return [];
        }
        $rows = [];
        foreach ($this->strings($this->parameters->get($parameter)) as $key => $value) {
            preg_match_all(self::DOC_LINK, $value, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $slug     = $match[1];
                $position = isset($match[2]) && $match[2] !== '' ? $slug . '#' . $match[2] : $slug;
                // Old: an anchor with its own rule, or any link into a chapter that moved (and no longer exists).
                if (!isset($rules[$position]) && (isset($chapters[$slug]) || !isset($rules[$slug]))) {
                    continue;
                }
                $final  = RedirectMapCompiler::resolvePosition($position, $rules) ?? '?';
                $rows[] = ['warning', $parameter . ' ' . $key, sprintf('Links to the old "%s": point it at "%s" directly (no redirect hop).', $position, $final)];
            }
        }

        return $rows;
    }

    /**
     * The links written in the chapters, in the source and in every translation file, whose target is an old slug or
     * a moved anchor (warning), a chapter that does not exist or an anchor missing in the target chapter (error).
     * Fenced code blocks are skipped; links that leave the manual (other repository docs, URLs, images) are not
     * checked. Anchors are looked up in the administrators' rendering of the target in the same locale.
     *
     * @param array<string, string> $rules
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function chapterLinks(array $rules): array
    {
        $directory = realpath($this->catalog->directory()) ?: $this->catalog->directory();
        $docsRoot  = dirname($directory);
        $locales   = [null];
        foreach ($this->catalog->locales() as $locale) {
            if ($this->catalog->translates($locale)) {
                $locales[] = $locale;
            }
        }
        $rows = [];
        $seen = [];
        foreach ($locales as $locale) {
            foreach ($this->catalog->all($locale) as $chapter) {
                $file = realpath($chapter->path);
                if ($file === false || isset($seen[$file])) {
                    continue;
                }
                $seen[$file] = true;
                $where       = substr($file, strlen($docsRoot) + 1);
                foreach ($this->links($file) as [$line, $target]) {
                    $problem = $this->linkProblem($target, $file, $docsRoot, basename($directory), $chapter, $locale, $rules);
                    if ($problem !== null) {
                        $rows[] = [$problem[0], $where . ':' . $line, $problem[1]];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * The Markdown link targets of a file with their line, outside fenced code blocks.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function links(string $file): array
    {
        $links = [];
        $fence = false;
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $index => $text) {
            if (preg_match('/^\s*(```|~~~)/', $text) === 1) {
                $fence = !$fence;

                continue;
            }
            if ($fence) {
                continue;
            }
            preg_match_all(self::MARKDOWN_LINK, $text, $matches);
            foreach ($matches[1] as $target) {
                $links[] = [$index + 1, $target];
            }
        }

        return $links;
    }

    /**
     * Why a link target is a problem (`[level, message]`), or null.
     *
     * @param array<string, string> $rules
     *
     * @return array{0: string, 1: string}|null
     */
    private function linkProblem(string $target, string $file, string $docsRoot, string $manual, ManualChapter $from, ?string $locale, array $rules): ?array
    {
        if (str_starts_with($target, '#')) {
            $slug   = $from->slug;
            $anchor = rawurldecode(substr($target, 1));
        } elseif (preg_match(self::CHAPTER_LINK, $target, $match) === 1) {
            $path = $this->normalise(substr(dirname($file), strlen($docsRoot) + 1) . '/' . $match[1]);
            if ($path === null || !str_starts_with($path, $manual . '/')) {
                return null;
            }
            $slug   = basename($path, '.md');
            $anchor = isset($match[2]) && $match[2] !== '' ? rawurldecode($match[2]) : null;
        } else {
            return null;
        }

        $position = $anchor === null ? $slug : $slug . '#' . $anchor;
        $chapter  = $this->catalog->find($slug, $locale);
        if (isset($rules[$position]) || (!$chapter instanceof ManualChapter && isset($rules[$slug]))) {
            return ['warning', sprintf('Links to the old "%s": point it at "%s" directly (no redirect hop).', $position, RedirectMapCompiler::resolvePosition($position, $rules) ?? '?')];
        }
        if (!$chapter instanceof ManualChapter) {
            return ['error', sprintf('Links to "%s", which is not a chapter.', $target)];
        }
        if ($anchor !== null && $anchor !== '' && !str_contains($this->renderer->render($chapter, true)->html, ' id="' . htmlspecialchars($anchor, ENT_QUOTES | ENT_HTML5) . '"')) {
            return ['error', sprintf('Links to the anchor "%s", which is not in chapter "%s"%s.', $anchor, $slug, $locale === null ? '' : ' (' . $locale . ')')];
        }

        return null;
    }

    /**
     * `manual/es/../guide/./x.md` → `manual/guide/x.md`; null when it climbs above the docs root.
     */
    private function normalise(string $path): ?string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * Every string of a parameter value, keyed by its path (`routes.admin_users.path`).
     *
     * @return array<string, string>
     */
    private function strings(mixed $value, string $path = ''): array
    {
        if (is_string($value)) {
            return [$path => $value];
        }
        $found = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $found += $this->strings($item, $path === '' ? (string) $key : $path . '.' . $key);
            }
        }

        return $found;
    }
}
