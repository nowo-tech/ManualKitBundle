<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Routing;

use Nowo\ManualKitBundle\Audience\ManualRoot;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function count;

/**
 * 1.6: the route of a chapter by its place in the tree — `nowo_manual_index` (home), `nowo_manual_chapter`
 * (`/{chapter}`), `nowo_manual_child` (`/{parent}/{chapter}`) or `nowo_manual_grandchild`
 * (`/{grandparent}/{parent}/{chapter}`). Every link the bundle prints goes through here. 1.13: in another manual root
 * the routes are that root's (`nowo_manual.portal.chapter`, {@see ManualRoot::routeName()}) and the root's `home`
 * chapter is its index.
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
     * The route name and parameters of a chapter (1.13: in a manual root; null = the default root).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public static function route(ManualChapter $chapter, ?ManualRoot $root = null): array
    {
        $name = static fn (string $route): string => $root instanceof ManualRoot ? $root->routeName($route) : $route;
        if (self::isHome($chapter, $root)) {
            return [$name(self::INDEX), []];
        }

        return match (count($chapter->ancestors)) {
            0       => [$name(self::CHAPTER), ['chapter' => $chapter->slug]],
            1       => [$name(self::CHILD), ['parent' => $chapter->ancestors[0], 'chapter' => $chapter->slug]],
            default => [$name(self::GRANDCHILD), ['grandparent' => $chapter->ancestors[0], 'parent' => $chapter->ancestors[1], 'chapter' => $chapter->slug]],
        };
    }

    /**
     * 1.13: whether the chapter is the home page of the root (its index): `README` in the default root, the root's
     * `home` elsewhere.
     */
    public static function isHome(ManualChapter $chapter, ?ManualRoot $root = null): bool
    {
        return $root instanceof ManualRoot ? $chapter->slug === $root->home && $chapter->ancestors === [] : $chapter->isHome();
    }

    /**
     * Generates the URL of a chapter (1.12: plus query parameters, such as `device`; 1.13: in a manual root).
     *
     * @param array<string, string> $query
     */
    public static function generate(UrlGeneratorInterface $urls, ManualChapter $chapter, array $query = [], ?ManualRoot $root = null): string
    {
        [$name, $parameters] = self::route($chapter, $root);

        return $urls->generate($name, [...$parameters, ...$query]);
    }
}
