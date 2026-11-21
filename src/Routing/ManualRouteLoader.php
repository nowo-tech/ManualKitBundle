<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Routing;

use InvalidArgumentException;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualRoot;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

use function is_string;
use function sprintf;

/**
 * 1.13: the routes of one manual root — `type: nowo_manual`, the resource being the root name:
 *
 *     nowo_manual_portal:
 *         resource: portal
 *         type: nowo_manual
 *         prefix: /portal/help
 *
 * The routes are the bundle's (`routes.php`), named `nowo_manual.<root>.<route>` (`nowo_manual.portal.chapter`) and
 * carrying the root as the `_nowo_manual_root` default, which a URL cannot set. `resource: default` gives the routes of
 * `routes.php` with their own names.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualRouteLoader extends Loader
{
    /** The route type. */
    public const string TYPE = 'nowo_manual';

    /**
     * Receives the audiences configuration (the roots).
     */
    public function __construct(private readonly ManualAudiences $audiences = new ManualAudiences(), ?string $env = null)
    {
        parent::__construct($env);
    }

    /**
     * {@inheritDoc}
     */
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $root = is_string($resource) ? $this->audiences->root($resource) : null;
        if (!$root instanceof ManualRoot) {
            throw new InvalidArgumentException(sprintf('The "%s" route resource must name a manual root: "default" or a key of "nowo_manual_kit.roots".', self::TYPE));
        }
        $imported = $this->import(__DIR__ . '/../Controller/', 'attribute');
        $routes   = new RouteCollection();
        if (!$imported instanceof RouteCollection) {
            return $routes; // @codeCoverageIgnore
        }
        foreach ($imported->all() as $name => $route) {
            $route->setDefault(ManualRoot::ATTRIBUTE, $root->name);
            $routes->add($root->routeName($name), $route, $imported->getPriority($name) ?? 0);
        }
        foreach ($imported->getResources() as $tracked) {
            $routes->addResource($tracked);
        }

        return $routes;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
