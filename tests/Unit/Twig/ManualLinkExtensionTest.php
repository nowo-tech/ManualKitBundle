<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Twig;

use Closure;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Twig\ManualLinkExtension;
use PHPUnit\Framework\TestCase;
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

        self::assertSame(['manual_href', 'manual_is_external', 'nowo_manual_layout', 'nowo_manual_asset'], array_keys($functions));
        self::assertSame('@NowoManualKitBundle/layout.html.twig', $functions['nowo_manual_layout']());
        self::assertSame('/bundles/nowomanualkit/css/manual.css', $functions['nowo_manual_asset']('/css/manual.css'));
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

    private function extension(bool $inApp): ManualLinkExtension
    {
        $routes = new RouteCollection();
        $routes->add('nowo_manual_index', new Route('/manual'));
        $routes->add('nowo_manual_chapter', new Route('/manual/{chapter}'));

        return new ManualLinkExtension(new ManualCatalog(dirname(__DIR__, 2) . '/Fixtures/manual'), new UrlGenerator($routes, new RequestContext()), self::DOCS, $inApp);
    }
}
