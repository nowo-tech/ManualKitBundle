<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function get_debug_type;
use function implode;
use function is_string;
use function json_encode;
use function ksort;
use function sprintf;
use function str_contains;
use function strcmp;
use function strval;
use function usort;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * 1.11: validates a redirect map and compiles it into the lookups {@see ChapterRedirectMap} serves.
 *
 * Rules: every key and target is `slug` or `slug#anchor` (internal only: never a URL, a scheme or `//`); no cycle;
 * chains are collapsed to the final target. Following a position (`slug` or `slug#anchor`):
 *
 * - an exact rule wins (`old#install` → `setup#docker`);
 * - otherwise `old#anchor` follows the chapter rule of `old`: to `new#anchor` when the target has no anchor (the
 *   fragment travels, as a browser keeps it through a 301), to the target's own anchor when it has one.
 *
 * The anchor lookups the client script needs are precomputed per landing chapter, so nothing is derived per request.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RedirectMapCompiler
{
    /**
     * Validates and compiles a map (old → new). Never throws: problems are listed in {@see CompiledRedirectMap::$errors}.
     *
     * @param array<mixed> $rules
     */
    public static function compile(array $rules): CompiledRedirectMap
    {
        $errors = [];
        $pairs  = [];
        $valid  = [];
        foreach ($rules as $from => $to) {
            $from = (string) $from;
            if (!is_string($to)) {
                $errors[] = sprintf('Redirect "%s": the target must be a chapter slug, got %s.', $from, get_debug_type($to));

                continue;
            }
            if (!RedirectTarget::isValid($from)) {
                $errors[] = sprintf('Redirect %s: the old path must be a chapter slug (letters, digits and dashes) with an optional #anchor.', self::quote($from));

                continue;
            }
            if (!RedirectTarget::isValid($to)) {
                $errors[] = sprintf('Redirect "%s" → %s: targets are internal chapter slugs with an optional #anchor only (never a URL, a scheme or "//").', $from, self::quote($to));

                continue;
            }
            // Pairs keep every slug a string ("404" would become an int array key).
            $pairs[] = [$from, $to];
        }
        usort($pairs, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
        foreach ($pairs as [$from, $to]) {
            $valid[$from] = $to;
        }

        $chapters = [];
        $moves    = [];
        $chains   = [];
        $arrivals = [];
        $own      = [];
        $names    = self::anchorNames($valid);
        foreach ($pairs as [$from, $to]) {
            [$final, $cycle] = self::resolve($from, $valid);
            if ($cycle !== null) {
                $errors[] = sprintf('Redirect cycle: %s.', implode(' → ', $cycle));

                continue;
            }
            if ($final !== $to) {
                $chains[$from] = $final;
            }
            if (!str_contains($from, '#')) {
                $moves[]         = [$from, $final];
                $chapters[$from] = $final;
            }
        }

        foreach ($moves as [$from, $final]) {
            [$page, $landing] = self::split($final);
            $moved            = [];
            foreach (self::chain($from, $valid) as $slug) {
                foreach ($names[$slug] ?? [] as $anchor) {
                    [$target, $cycle] = self::resolve($from . '#' . $anchor, $valid);
                    if ($cycle !== null) {
                        $errors[] = sprintf('Redirect cycle: %s.', implode(' → ', $cycle));

                        continue;
                    }
                    if ($target !== $page . '#' . $anchor) {
                        $moved[$anchor] = $target;
                    }
                }
            }
            if ($moved !== []) {
                if ($landing !== null) {
                    // The 301 then carries no fragment, so the script also places a reader who came without one.
                    $moved[''] = $final;
                }
                ksort($moved);
                $arrivals[$page][$from] = $moved;
            }
        }

        foreach ($pairs as [$from]) {
            [$slug, $anchor] = self::split($from);
            if ($anchor === null || isset($valid[$slug])) {
                continue;
            }
            [$target, $cycle] = self::resolve($from, $valid);
            if ($cycle === null) {
                $own[$slug][$anchor] = $target;
            }
        }

        return new CompiledRedirectMap($valid, $chapters, $arrivals, $own, $chains, array_values(array_unique($errors)));
    }

    /**
     * Where a position (`slug` or `slug#anchor`) ends up under a valid map, or null when no rule applies to it (or on
     * a cycle, which a compiled map never has). With `$exists`, the walk stops at the first hop that lands on a
     * chapter that still exists: an existing chapter always wins over a rule written for its slug.
     *
     * @param array<string, string> $rules
     * @param (callable(string): bool)|null $exists whether a chapter slug still exists
     */
    public static function resolvePosition(string $position, array $rules, ?callable $exists = null): ?string
    {
        [$final, $cycle] = self::resolve($position, $rules, $exists);

        return $cycle !== null || $final === $position ? null : $final;
    }

    /**
     * The final position of `$position` and, on a cycle, the positions that form it.
     *
     * @param array<string, string> $rules
     * @param (callable(string): bool)|null $exists stop at a hop that lands on an existing chapter
     *
     * @return array{0: string, 1: list<string>|null}
     */
    private static function resolve(string $position, array $rules, ?callable $exists = null): array
    {
        $seen    = [$position => true];
        $path    = [$position];
        $current = $position;
        while (($next = self::step($current, $rules)) !== null) {
            $path[] = $next;
            if (isset($seen[$next])) {
                return [$current, $path];
            }
            $seen[$next] = true;
            $current     = $next;
            if ($exists !== null && $exists(self::split($next)[0])) {
                break;
            }
        }

        return [$current, null];
    }

    /**
     * One hop: the exact rule, else (for `slug#anchor`) the chapter rule of `slug`.
     *
     * @param array<string, string> $rules
     */
    private static function step(string $position, array $rules): ?string
    {
        if (isset($rules[$position])) {
            return $rules[$position];
        }
        [$slug, $anchor] = self::split($position);
        if ($anchor === null || !isset($rules[$slug])) {
            return null;
        }
        $to = $rules[$slug];

        return str_contains($to, '#') ? $to : $to . '#' . $anchor;
    }

    /**
     * The chapter slugs a chapter redirect walks through (itself first), following chapter rules only.
     *
     * @param array<string, string> $rules
     *
     * @return list<string>
     */
    private static function chain(string $slug, array $rules): array
    {
        $chain   = [];
        $current = $slug;
        while (isset($rules[$current]) && !isset($chain[$current])) {
            $chain[$current] = true;
            $current         = self::split($rules[$current])[0];
        }

        return array_map(strval(...), array_keys($chain));
    }

    /**
     * The anchors with their own rule, per chapter slug.
     *
     * @param array<string, string> $rules
     *
     * @return array<string, list<string>>
     */
    private static function anchorNames(array $rules): array
    {
        $names = [];
        foreach (array_keys($rules) as $from) {
            [$slug, $anchor] = self::split((string) $from);
            if ($anchor !== null) {
                $names[$slug][] = $anchor;
            }
        }

        return $names;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function split(string $position): array
    {
        if (!str_contains($position, '#')) {
            return [$position, null];
        }
        [$slug, $anchor] = explode('#', $position, 2);

        return [$slug, $anchor];
    }

    private static function quote(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
