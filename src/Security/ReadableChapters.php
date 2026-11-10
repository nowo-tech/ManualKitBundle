<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;

use function array_filter;
use function array_intersect;
use function array_values;
use function in_array;

/**
 * 1.9: applies {@see ManualAccessInterface::canReadChapter()} to a list of chapters; a chapter whose parent (or
 * grandparent) is refused is refused too.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ReadableChapters
{
    /**
     * The chapters of `$chapters` the user may open, in the same order.
     *
     * @param list<ManualChapter> $chapters
     * @param list<ManualChapter> $all the whole manual (for the parents that are not in `$chapters`)
     *
     * @return list<ManualChapter>
     */
    public static function filter(array $chapters, array $all, ManualAccessInterface $access): array
    {
        $refused = [];
        foreach ($all as $chapter) {
            if (!$access->canReadChapter($chapter)) {
                $refused[] = $chapter->slug;
            }
        }

        return array_values(array_filter(
            $chapters,
            static fn (ManualChapter $chapter): bool => !in_array($chapter->slug, $refused, true) && array_intersect($chapter->ancestors, $refused) === [],
        ));
    }
}
