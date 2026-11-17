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
use function count;
use function htmlspecialchars;
use function is_array;
use function is_file;
use function is_string;
use function preg_match_all;
use function sprintf;
use function str_contains;

use const ENT_HTML5;
use const ENT_QUOTES;
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

        $errors   = count(array_filter($rows, static fn (array $row): bool => $row[0] === 'error'));
        $warnings = count($rows) - $errors;
        if ($rows === []) {
            $io->success(sprintf('%d redirect rule(s) checked: every target exists, no chain, no old slug linked.', count($rules)));

            return Command::SUCCESS;
        }
        $io->table(['Level', 'Rule', 'Problem'], $rows);
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
