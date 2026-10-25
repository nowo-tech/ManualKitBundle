<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use function Symfony\Component\String\u;

/**
 * How the manual is matched by a search box: case- and accent-insensitive ("contrasena" finds "Contraseña"), every
 * word of the query somewhere in the title or the tags.
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
}
