<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use Closure;

use function count;
use function preg_quote;
use function preg_replace_callback;
use function preg_split;

/**
 * The `<!-- name: value -->…<!-- /name -->` blocks of a chapter (`audience`, 1.12 `device`), invisible on GitHub. One
 * set of parsing rules for every kind: the value is one lower-case word (letters, any case accepted), a block ends at
 * the first closing marker of its kind (blocks of one kind do not nest; blocks of different kinds do), markers are
 * read anywhere in the file, fenced code included (the audience filter fails closed there: a sample of an admin
 * block renders empty for members; the device markers in code are kept literal by DeviceBlocks). A marker left without its pair is a "stray" one: what to do with it is the caller's rule
 * (the audience filter fails closed, device blocks fall back to shared content).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CommentBlocks
{
    /**
     * The balanced blocks of a kind: group 1 is the value, group 2 the content. 1.13: with `$list`, the value is one
     * or several comma-separated names (`admin, developer`; letters, digits and `_`, starting with a letter).
     */
    public static function blockPattern(string $kind, bool $list = false): string
    {
        $kind  = preg_quote($kind, '/');
        $value = $list ? '[a-z][a-z0-9_]*(?:\s*,\s*[a-z][a-z0-9_]*)*' : '[a-z]+';

        return '/<!--\s*' . $kind . ':\s*(' . $value . ')\s*-->(.*?)<!--\s*\/' . $kind . '\s*-->/si';
    }

    /**
     * Any marker of a kind (an opening or closing comment, well-formed or not).
     */
    public static function markerPattern(string $kind): string
    {
        return '/<!--\s*\/?\s*' . preg_quote($kind, '/') . '.*?-->/si';
    }

    /**
     * Replaces every balanced block of a kind with what `$replace` returns for it.
     *
     * @param Closure(string, string): string $replace receives the value (lower case) and the content
     * @param bool $list 1.13: the value may list several names ({@see self::blockPattern()})
     */
    public static function replace(string $markdown, string $kind, Closure $replace, bool $list = false): string
    {
        return (string) preg_replace_callback(
            self::blockPattern($kind, $list),
            static fn (array $block): string => $replace(strtolower($block[1]), $block[2]),
            $markdown,
        );
    }

    /**
     * The text split at the markers of a kind still there: one part when there is none.
     *
     * @return list<string>
     */
    public static function splitAtMarkers(string $markdown, string $kind): array
    {
        $parts = preg_split(self::markerPattern($kind), $markdown);

        return $parts === false || count($parts) === 0 ? [$markdown] : $parts;
    }
}
