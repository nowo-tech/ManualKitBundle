<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function sprintf;

/**
 * REQ-SF-004 rule 7: the manual routes are opt-in (import of the bundle routes.php with a prefix); the host's default
 * `controllers: { resource: routing.controllers }` import must not register them (they would land on `/{chapter}`).
 */
final class HostControllersRoutesTest extends TestCase
{
    public function testBundleRoutesAreNotRegisteredWithoutTheRoutesImport(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $kernel = new TestKernel([], true);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);
            $routes = $router->getRouteCollection();

            foreach (['nowo_manual_index', 'nowo_manual_chapter', 'nowo_manual_chapter_md', 'nowo_manual_image'] as $name) {
                self::assertNull($routes->get($name), sprintf('Route "%s" must only exist when the host imports the bundle routes.php.', $name));
            }
            foreach ($routes as $name => $route) {
                self::assertStringStartsNotWith('nowo_manual', $name);
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
