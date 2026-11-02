<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Routing;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function count;

/**
 * 1.6: the route of a chapter by its place in the tree — `nowo_manual_index` (home), `nowo_manual_chapter`
 * (`/{chapter}`), `nowo_manual_child` (`/{parent}/{chapter}`) or `nowo_manual_grandchild`
 * (`/{grandparent}/{parent}/{chapter}`). Every link the bundle prints goes through here.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualRoutes
{
    public const string INDEX      = 'nowo_manual_index';
    public const string CHAPTER    = 'nowo_manual_chapter';
    public const string CHILD      = 'nowo_manual_child';
    public const string GRANDCHILD = 'nowo_manual_grandchild';

    /** One URL segment of a chapter (a slug). */
    public const string SEGMENT = '[A-Za-z0-9][A-Za-z0-9-]*';

    /**
     * The route name and parameters of a chapter.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public static function route(ManualChapter $chapter): array
    {
        if ($chapter->isHome()) {
            return [self::INDEX, []];
        }

        return match (count($chapter->ancestors)) {
            0       => [self::CHAPTER, ['chapter' => $chapter->slug]],
            1       => [self::CHILD, ['parent' => $chapter->ancestors[0], 'chapter' => $chapter->slug]],
            default => [self::GRANDCHILD, ['grandparent' => $chapter->ancestors[0], 'parent' => $chapter->ancestors[1], 'chapter' => $chapter->slug]],
        };
    }

    /**
     * Generates the URL of a chapter.
     */
    public static function generate(UrlGeneratorInterface $urls, ManualChapter $chapter): string
    {
        [$name, $parameters] = self::route($chapter);

        return $urls->generate($name, $parameters);
    }
}
