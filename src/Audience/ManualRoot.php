<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * 1.13: one manual root — the chapters of the manifest that some audiences of the root may read, served under its own
 * route import (and, with `firewalls`, only inside those firewalls). The default root (`default`) is the manual of
 * `nowo_manual_kit.role`; every other root comes from `nowo_manual_kit.roots`. A chapter belongs to a root when every
 * level of it (its parents and itself) names at least one audience of the root, so no chapter, link, image or search
 * result crosses from one root to another unless its manifest row names an audience of both.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualRoot
{
    /** The name of the root of `nowo_manual_kit.role` (the routes of `routes.php`). */
    public const string DEFAULT = 'default';

    /** Request attribute (a route default set by the `nowo_manual` route loader) naming the root of a manual route. */
    public const string ATTRIBUTE = '_nowo_manual_root';

    /** Prefix of every route name of the default root. */
    private const string ROUTE_PREFIX = 'nowo_manual_';

    /**
     * @param string $name `default` or a key of `nowo_manual_kit.roots`
     * @param list<string> $audiences the audiences this root serves (`member` only in the default root)
     * @param string $role who may open the root at all ('' = everybody); for the default root, `nowo_manual_kit.role`
     * @param string $home the slug of the chapter the root's index serves
     * @param list<string> $firewalls the firewalls this root is served in ([] = any firewall no other root claims)
     */
    public function __construct(
        public string $name,
        public array $audiences,
        public string $role = '',
        public string $home = 'README',
        public array $firewalls = [],
    ) {
    }

    /**
     * Whether this is the default root (the routes of `routes.php`).
     */
    public function isDefault(): bool
    {
        return $this->name === self::DEFAULT;
    }

    /**
     * Whether the root serves this audience.
     */
    public function serves(string $audience): bool
    {
        return in_array($audience, $this->audiences, true);
    }

    /**
     * The name of one of this root's routes: `nowo_manual_chapter` in the default root, `nowo_manual.portal.chapter`
     * in the root `portal`.
     */
    public function routeName(string $route): string
    {
        if ($this->isDefault() || !str_starts_with($route, self::ROUTE_PREFIX)) {
            return $route;
        }

        return 'nowo_manual.' . $this->name . '.' . substr($route, strlen(self::ROUTE_PREFIX));
    }
}
