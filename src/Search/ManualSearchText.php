<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use function Symfony\Component\String\u;

/**
 * How the manual is matched by a search box: case- and accent-insensitive ("contrasena" finds "Contraseña"), every
 * word of the query somewhere in the title or the tags. 1.15: matchesAny() tries synonym alternatives too.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualSearchText
{
    /**
     * Folds text for matching: ASCII, lower case, collapsed whitespace.
     */
    public static function fold(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', u($text)->ascii()->lower()->toString()));
    }

    /**
     * @param list<string> $tags
     */
    public static function matches(string $query, string $title, array $tags = []): bool
    {
        $haystack = self::fold($title . ' ' . implode(' ', $tags));
        $words    = array_filter(explode(' ', self::fold($query)), static fn (string $word): bool => $word !== '');

        return $words !== [] && array_all($words, static fn (string $word): bool => str_contains($haystack, $word));
    }

    /**
     * Whether any of the texts matches (1.15: the typed text or one of its synonym alternatives), each by matches().
     *
     * @param list<string> $queries The text and its alternatives
     * @param list<string> $tags
     */
    public static function matchesAny(array $queries, string $title, array $tags = []): bool
    {
        return self::matchesWords(self::words($queries), $title, $tags);
    }

    /**
     * The folded words of each query (1.15), computed once per search for matchesWords(); queries without a word are
     * dropped.
     *
     * @param list<string> $queries The text and its alternatives
     *
     * @return list<non-empty-list<string>>
     */
    public static function words(array $queries): array
    {
        $words = [];
        foreach ($queries as $query) {
            $folded = array_values(array_filter(explode(' ', self::fold($query)), static fn (string $word): bool => $word !== ''));
            if ($folded !== []) {
                $words[] = $folded;
            }
        }

        return $words;
    }

    /**
     * Whether every word of at least one query (words()) occurs in the folded title and tags (1.15).
     *
     * @param list<non-empty-list<string>> $queries Folded words per query
     * @param list<string> $tags
     */
    public static function matchesWords(array $queries, string $title, array $tags = []): bool
    {
        $haystack = self::fold($title . ' ' . implode(' ', $tags));

        return array_any($queries, static fn (array $words): bool => array_all($words, static fn (string $word): bool => str_contains($haystack, $word)));
    }
}
