<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Twig;

use Closure;
use LogicException;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Twig\ManualLinkExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\TwigFunction;

use function dirname;

final class ManualLinkExtensionTest extends TestCase
{
    private const string DOCS = 'https://github.com/x/y/blob/main/docs/';

    public function testManualChaptersOpenInAppAndEverythingElseOnThePublishedDocs(): void
    {
        $extension = $this->extension(true);
        $functions = [];
        foreach ($extension->getFunctions() as $function) {
            self::assertInstanceOf(TwigFunction::class, $function);
            $callable = $function->getCallable();
            self::assertInstanceOf(Closure::class, $callable);
            $functions[$function->getName()] = $callable;
        }

        self::assertSame(['manual_href', 'manual_is_external', 'nowo_manual_layout', 'nowo_manual_asset', 'nowo_manual_asset_package', 'nowo_manual_standalone_lightbox'], array_keys($functions));
        self::assertSame('@NowoManualKitBundle/layout.html.twig', $functions['nowo_manual_layout']());
        // Resolved by the `nowo_manual_kit` asset package (here on a CDN), never a hard-coded /bundles path.
        self::assertSame('https://cdn.example/manual/css/manual.css', $functions['nowo_manual_asset']('/css/manual.css'));
        self::assertSame('nowo_manual_kit', $functions['nowo_manual_asset_package']());
        self::assertFalse($functions['nowo_manual_standalone_lightbox']());
        self::assertSame('/manual', $extension->href('manual/README.md'));
        self::assertSame('/manual/02-admin#users', $extension->href('manual/02-admin.md#users'));
        self::assertFalse($extension->isExternal('manual/02-admin.md'));

        foreach (['DSN.md', 'manual/99-unlisted.md', 'manual/images/a.webp', 'product/SMS.md#x'] as $doc) {
            self::assertSame(self::DOCS . $doc, $extension->href($doc), $doc);
            self::assertTrue($extension->isExternal($doc), $doc);
        }
    }

    public function testEveryLinkGoesToThePublishedDocsWhenTheInAppManualIsOff(): void
    {
        $extension = $this->extension(false);

        self::assertSame(self::DOCS . 'manual/02-admin.md#users', $extension->href('manual/02-admin.md#users'));
        self::assertTrue($extension->isExternal('manual/README.md'));
    }

    public function testTheAssetHelperNeedsTheAssetComponent(): void
    {
        $routes    = new RouteCollection();
        $extension = new ManualLinkExtension(new ManualCatalog(dirname(__DIR__, 2) . '/Fixtures/manual'), new UrlGenerator($routes, new RequestContext()), standaloneLightbox: true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('framework.assets');
        $extension->asset('css/manual.css');
    }

    private function extension(bool $inApp): ManualLinkExtension
    {
        $routes = new RouteCollection();
        $routes->add('nowo_manual_index', new Route('/manual'));
        $routes->add('nowo_manual_chapter', new Route('/manual/{chapter}'));

        $packages = new Packages(null, ['nowo_manual_kit' => new UrlPackage('https://cdn.example/manual', new EmptyVersionStrategy())]);

        return new ManualLinkExtension(new ManualCatalog(dirname(__DIR__, 2) . '/Fixtures/manual'), new UrlGenerator($routes, new RequestContext()), self::DOCS, $inApp, packages: $packages);
    }
}
