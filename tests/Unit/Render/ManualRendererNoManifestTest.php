<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Render;

use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.10.1: a host catalog may serve chapters from a directory without `manifest.yaml`; the render cache key must not
 * depend on the manifest then (before, filemtime() warned and the render failed).
 */
final class ManualRendererNoManifestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/nowo-manual-no-manifest-' . uniqid('', true);
        (new Filesystem())->mkdir($this->directory);
        file_put_contents($this->directory . '/intro.md', "# Intro\n\n## Section\n\nHello **host**.\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testRendersAChapterOfACatalogWithoutManifestAndCachesIt(): void
    {
        $catalog = $this->createStub(ManualCatalogInterface::class);
        $catalog->method('directory')->willReturn($this->directory);
        $catalog->method('sourceLocale')->willReturn('en');
        $catalog->method('all')->willReturn([]);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('getContext')->willReturn(new RequestContext());
        $cache = new ArrayAdapter();

        $chapter  = new ManualChapter('intro', 'guide', false, 'book', 'Intro', $this->directory . '/intro.md');
        $renderer = new ManualRenderer($catalog, $urls, $cache);

        $first = $renderer->render($chapter, false);
        self::assertStringContainsString('<strong>host</strong>', $first->html);
        self::assertCount(1, $cache->getValues(), 'one cache entry');
        self::assertEquals($first, $renderer->render($chapter, false), 'the second render is served from the cache');
    }
}
