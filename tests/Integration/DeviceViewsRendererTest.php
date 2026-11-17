<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;
use Nowo\ManualKitBundle\Render\DeviceViews;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * 1.12: device blocks, phone companions and the TOC devices, rendered with `device_views.enabled: true`.
 */
final class DeviceViewsRendererTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/devices/manual';

    private string $directory = '';

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel();
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/manual-devices-' . bin2hex(random_bytes(4));
        new Filesystem()->mirror(self::FIXTURES, $this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    public function testDeviceBlocksBecomeLabelledWrappersAndTheTocKnowsTheirDevice(): void
    {
        $rendered = $this->render('01-tasks', true);
        $page     = $this->page($rendered);

        self::assertTrue($rendered->deviceViews);
        self::assertCount(1, $page->filter('div.manual-device.manual-device--desktop[data-nowo-manual-device="desktop"]'));
        self::assertCount(1, $page->filter('div.manual-device.manual-device--phone[data-nowo-manual-device="phone"]'));
        self::assertSame('Desktop:', $page->filter('div.manual-device--desktop > p.manual-device__label')->text());
        self::assertSame('Phone:', $page->filter('div.manual-device--phone > p.manual-device__label')->text());
        self::assertSame('filter-from-the-sidebar', $page->filter('div.manual-device--desktop h3')->attr('id'));
        self::assertStringContainsString('Admins also see', $page->filter('div.manual-device--phone')->text(), 'an audience block inside a device block');
        self::assertStringNotContainsString('nowomanualdevice', $rendered->html, 'no token reaches the page');
        self::assertStringNotContainsString('device:', $rendered->html);
        self::assertStringNotContainsString('data-nowo-manual-companion', $rendered->html);
        self::assertStringContainsString('Tablet text is shared', $page->filter('.manual-prose > p, div > p')->text('') . $rendered->html);
        self::assertCount(0, $page->filter('[data-nowo-manual-device="tablet"], .manual-device--tablet'), 'unknown device names are rejected');

        self::assertSame([
            ['level' => 2, 'id' => 'open-the-board', 'text' => 'Open the board', 'tags' => ['kanban']],
            ['level' => 3, 'id' => 'filter-from-the-sidebar', 'text' => 'Filter from the sidebar', 'tags' => ['sidebar'], 'device' => 'desktop'],
            ['level' => 3, 'id' => 'filter-from-the-bottom-sheet', 'text' => 'Filter from the bottom sheet', 'tags' => ['sheet'], 'device' => 'phone'],
            ['level' => 2, 'id' => 'inline-image', 'text' => 'Inline image', 'tags' => []],
            ['level' => 2, 'id' => 'done', 'text' => 'Done', 'tags' => []],
        ], $rendered->toc);

        $member = $this->page($this->render('01-tasks', false));
        self::assertStringNotContainsString('Admins also see', $member->filter('div.manual-device--phone')->text(), 'the audience rule still applies inside a device block');
    }

    public function testPhoneCompanionsSwapOrTwinTheImage(): void
    {
        $page = $this->page($this->render('01-tasks', false));

        $desktop = $page->filter('figure[data-nowo-manual-device="desktop"]');
        $phone   = $page->filter('figure[data-nowo-manual-device="phone"]');
        self::assertSame('/en/manual/images/board.png', $desktop->filter('img')->attr('src'));
        self::assertSame(['40', '20', null], [$desktop->filter('img')->attr('width'), $desktop->filter('img')->attr('height'), $desktop->filter('img')->attr('class')]);
        self::assertSame('/en/manual/images/board.phone.png', $phone->filter('img')->attr('src'));
        self::assertSame('/en/manual/images/board.phone.png', $phone->filter('a.manual-zoom')->attr('href'));
        self::assertSame(['20', '40', ManualRenderer::PORTRAIT], [$phone->filter('img')->attr('width'), $phone->filter('img')->attr('height'), $phone->filter('img')->attr('class')]);
        self::assertSame('manual-figure manual-figure--portrait', $phone->attr('class'));
        self::assertSame('Your board', $phone->filter('figcaption')->text());
        self::assertCount(0, $page->filter('.manual-gallery'), 'a companion takes its image\'s place, not a gallery slot');

        // Inside a phone block the companion replaces the image; menu.png has none and stays.
        self::assertSame('/en/manual/images/drawer.phone.png', $page->filter('div.manual-device--phone figure img')->attr('src'));
        self::assertNull($page->filter('div.manual-device--phone figure')->attr('data-nowo-manual-device'));
        self::assertCount(1, $page->filter('img[alt="the menu"]'));
        self::assertNull($page->filter('img[alt="the menu"]')->attr('data-nowo-manual-device'));
    }

    public function testCompanionsInDesktopBlocksInlineGalleriesAndOddFiles(): void
    {
        copy($this->directory . '/images/drawer.phone.png', $this->directory . '/images/menu-phone.png');
        file_put_contents($this->directory . '/images/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        file_put_contents($this->directory . '/images/logo-phone.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        file_put_contents($this->directory . '/images/menu.phone-phone.png', 'not an image');
        file_put_contents($this->directory . '/02-plain.md', <<<'MD'
            # Plain

            <!-- device: desktop -->
            ![Desk](images/menu.png)
            <!-- /device -->

            Inline ![menu](images/menu.png) and ![remote](https://example.com/x.png).

            ![A](images/menu.png) ![B](images/board.png)

            ![Logo](images/logo.svg)

            ![Twice](images/menu-phone.png) ![Gone](images/missing.png)
            MD);

        $rendered = $this->render('02-plain', false, '-phone');
        $page     = $this->page($rendered);

        self::assertSame('/en/manual/images/menu.png', $page->filter('div.manual-device--desktop img')->attr('src'), 'no companion inside a desktop block');
        self::assertSame(['desktop', 'phone'], $page->filter('p img[alt="menu"]')->each(static fn (Crawler $image): ?string => $image->attr('data-nowo-manual-device')), 'an inline image gets an inline twin');
        self::assertSame('/en/manual/images/menu-phone.png', $page->filter('p img[alt="menu"]')->last()->attr('src'));
        self::assertCount(1, $page->filter('img[alt="remote"]'));
        $gallery = $page->filter('div.manual-gallery')->first();
        self::assertCount(2, $page->filter('div.manual-gallery'));
        self::assertSame(['desktop', 'phone', null], $gallery->filter('figure')->each(static fn (Crawler $figure): ?string => $figure->attr('data-nowo-manual-device')));
        $logo = $page->filter('figure[data-nowo-manual-device="phone"] img[alt="Logo"]');
        self::assertSame('/en/manual/images/logo-phone.svg', $logo->attr('src'));
        self::assertNull($logo->attr('width'), 'an SVG companion has no intrinsic size');
        self::assertCount(1, $page->filter('img[alt="Twice"]'), 'a companion has no companion of its own');
        self::assertCount(1, $page->filter('img[alt="Gone"]'));
        self::assertTrue($rendered->deviceViews, 'companions alone are device content');
    }

    public function testACompanionOutsideTheImagesDirectoryIsIgnored(): void
    {
        $outside = sys_get_temp_dir() . '/manual-devices-outside-' . bin2hex(random_bytes(4)) . '.png';
        copy($this->directory . '/images/board.phone.png', $outside);
        try {
            unlink($this->directory . '/images/board.phone.png');
            symlink($outside, $this->directory . '/images/board.phone.png');
            mkdir($this->directory . '/images/menu.phone.png');

            $page = $this->page($this->render('01-tasks', false));
            self::assertCount(0, $page->filter('img[src$="board.phone.png"]'), 'never through a symbolic link out of the images directory');

            $plain = $this->render('02-plain', false);
            self::assertCount(0, $this->page($plain)->filter('img[src$="menu.phone.png"]'), 'a directory is not a companion');
            self::assertFalse($plain->deviceViews);
        } finally {
            @unlink($outside);
        }
    }

    public function testMalformedAndNestedMarkersShareTheirContent(): void
    {
        file_put_contents($this->directory . '/02-plain.md', <<<'MD'
            # Plain

            <!-- device: phone -->
            Outer phone.
            <!-- device: desktop -->
            Nested desktop.
            <!-- /device -->
            After the nest.
            <!-- /device -->

            - item
              <!-- device: phone -->
              In a list.
              <!-- /device -->

            <!-- device phone -->
            Typo opening.

            <!-- device: PHONE -->
            Upper case name.
            <!-- /device -->

            <!-- device: phone -->
            <script>alert(1)</script><img src=x onerror=alert(1)> **Bold** <a href="javascript:alert(1)">x</a>
            <!-- /device -->

            Closing text without opener.
            <!-- /device -->

            <div>
            <!-- device: phone -->
            Split by HTML.
            </div>
            <!-- /device -->

            ```markdown
            <!-- device: phone -->
            Sample.
            <!-- /device -->
            ```
            MD);

        $rendered = $this->render('02-plain', false);
        $page     = $this->page($rendered);
        $phone    = $page->filter('div.manual-device--phone');

        self::assertStringContainsString('Outer phone.', $phone->eq(0)->text());
        self::assertStringContainsString('Nested desktop.', $phone->eq(0)->text(), 'blocks of one kind do not nest');
        self::assertStringNotContainsString('After the nest.', $phone->eq(0)->text());
        self::assertStringContainsString('After the nest.', $rendered->html, 'shared');
        self::assertStringContainsString('In a list.', $rendered->html);
        self::assertStringContainsString('Typo opening.', $rendered->html);
        self::assertStringContainsString('Upper case name.', $phone->eq(1)->text(), 'names are case-insensitive, like audience values');
        self::assertStringContainsString('Split by HTML.', $rendered->html);
        self::assertCount(0, $page->filter('div.manual-device:contains("Split by HTML.")'), 'markers in different elements share their content');
        self::assertSame("<!-- device: phone -->\nSample.\n<!-- /device -->\n", $page->filter('pre code')->text(null, false), 'a marker inside code is shown as written');
        self::assertStringNotContainsString('<script', $rendered->html);
        self::assertStringNotContainsString('onerror', $rendered->html);
        self::assertStringNotContainsString('javascript:', $rendered->html);
        self::assertStringContainsString('Closing text without opener.', $rendered->html);
        self::assertStringNotContainsString('nowomanualdevice', $rendered->html);
        self::assertStringNotContainsString('<!--', $rendered->html);
    }

    public function testMarkersInCodeStayLiteralAndNoTokenReachesThePage(): void
    {
        file_put_contents($this->directory . '/02-plain.md', <<<'MD'
            # Plain

            Write `<!-- device: phone -->` and close with `<!-- /device -->`.

            <pre>
            <!-- device: phone -->
            raw sample
            <!-- /device -->
            </pre>

            ``double `tick` <!-- device: desktop -->`` still text.
            MD);

        $rendered = $this->render('02-plain', false);
        $page     = $this->page($rendered);

        self::assertFalse($rendered->deviceViews);
        self::assertSame(['<!-- device: phone -->', '<!-- /device -->', 'double `tick` <!-- device: desktop -->'], $page->filter('p code')->each(static fn (Crawler $code): string => $code->text()));
        self::assertStringContainsString('and close with', $page->filter('p')->first()->text());
        self::assertStringContainsString('raw sample', $rendered->html);
        self::assertStringNotContainsString('nowomanualdevice', $rendered->html);
        self::assertStringContainsString('still text', $rendered->html);
        self::assertCount(0, $page->filter('div.manual-device'));
    }

    public function testMarkersInsideTablesQuotesAndListsAreDroppedAndTheContentStaysInPlace(): void
    {
        file_put_contents($this->directory . '/02-plain.md', <<<'MD'
            # Plain

            | a | b |
            | - | - |
            | <!-- device: phone -->x | y<!-- /device --> |

            > <!-- device: phone -->
            > quoted
            > <!-- /device -->

            - item
              <!-- device: phone -->
              in a list
              <!-- /device -->

            <!-- device: phone --> inline start
            text after the opening
            <!-- /device -->

            <!-- device: phone -->
            Whole block.
            <!-- /device -->
            MD);

        $rendered = $this->render('02-plain', false);
        $page     = $this->page($rendered);

        self::assertSame(['x', 'y'], $page->filter('table td')->each(static fn (Crawler $cell): string => trim($cell->text())));
        self::assertSame('quoted', trim($page->filter('blockquote')->text()));
        self::assertCount(1, $page->filter('blockquote'));
        self::assertSame('item in a list', preg_replace('/\s+/', ' ', trim($page->filter('ul li')->text())));
        self::assertCount(1, $page->filter('ul'));
        self::assertStringContainsString('inline start', $rendered->html);
        self::assertStringContainsString('text after the opening', $rendered->html);
        self::assertCount(1, $page->filter('div.manual-device'), 'only the whole top-level block is a device block');
        self::assertSame('Whole block.', trim($page->filter('div.manual-device--phone')->text()));
        self::assertStringNotContainsString('nowomanualdevice', $rendered->html);
        self::assertStringNotContainsString('<!--', $rendered->html);
    }

    public function testPlaceholdersCannotBeForgedByTheChapterText(): void
    {
        file_put_contents($this->directory . '/02-plain.md', "# Plain\n\n```\nFENCED\n```\n\nForged: \x000\x00 and \x00\x00.\n");

        $rendered = $this->render('02-plain', false);

        self::assertSame(1, substr_count($rendered->html, 'FENCED'), 'a NUL placeholder written in the chapter does not copy the fence');
    }

    public function testACompanionAddedLaterChangesTheCacheKey(): void
    {
        $catalog  = new ManualCatalog($this->directory);
        $renderer = new ManualRenderer($catalog, $this->urls(), new ArrayAdapter(), '', new DeviceViews(true));
        $chapter  = $catalog->find('01-tasks') ?? self::fail('fixture');
        $images   = $this->directory . '/images';
        unlink($images . '/board.phone.png');
        touch($images, time() - 1000);
        clearstatcache();

        $before = $renderer->render($chapter, false)->html;
        self::assertStringNotContainsString('board.phone.png', $before);

        copy(self::FIXTURES . '/images/board.phone.png', $images . '/board.phone.png');
        touch($images, time() - 500);
        clearstatcache();
        self::assertStringContainsString('board.phone.png', $renderer->render($chapter, false)->html, 'the same renderer and cache see the new companion');

        unlink($images . '/board.phone.png');
        touch($images, time() - 100);
        clearstatcache();
        self::assertStringNotContainsString('board.phone.png', $renderer->render($chapter, false)->html);
    }

    public function testARecentImagesChangeGivesTheCachedChapterAShortLife(): void
    {
        $catalog = new ManualCatalog($this->directory);
        $chapter = $catalog->find('01-tasks') ?? self::fail('fixture');
        $expiry  = function (bool $enabled, int $mtime) use ($catalog, $chapter): int {
            touch($this->directory . '/images', $mtime);
            clearstatcache();
            $cache = new ArrayAdapter();
            new ManualRenderer($catalog, $this->urls(), $cache, '', new DeviceViews($enabled))->render($chapter, false);
            $item = $cache->getItem((string) array_key_first($cache->getValues()));

            return (int) $item->getMetadata()[ItemInterface::METADATA_EXPIRY] - time();
        };

        self::assertLessThanOrEqual(2, $expiry(true, time()));
        self::assertGreaterThan(80000, $expiry(true, time() - 100));
        self::assertGreaterThan(80000, $expiry(false, time()), 'unchanged when device views are off');
    }

    public function testPlainChaptersHaveNoDeviceContentAndRenderingIsRepeatable(): void
    {
        file_put_contents($this->directory . '/02-plain.md', "# Plain\n\nJust text.\n");
        $plain = $this->render('02-plain', false);
        self::assertFalse($plain->deviceViews);
        self::assertSame("<p>Just text.</p>\n", $plain->html);

        // A fresh token per rendering, the same HTML every time (worker mode: nothing kept between renderings).
        $catalog  = new ManualCatalog($this->directory);
        $renderer = new ManualRenderer($catalog, $this->urls(), new ArrayAdapter(), '', new DeviceViews(true));
        $chapter  = $catalog->find('01-tasks') ?? self::fail('fixture');
        $first    = $renderer->render($chapter, true)->html;
        $other    = new ManualRenderer($catalog, $this->urls(), new ArrayAdapter(), '', new DeviceViews(true));
        self::assertSame($first, $other->render($chapter, true)->html);
    }

    public function testTheCacheKeyDependsOnTheDeviceSettings(): void
    {
        $catalog = new ManualCatalog($this->directory);
        $cache   = new ArrayAdapter();
        $chapter = $catalog->find('01-tasks') ?? self::fail('fixture');
        $off     = new ManualRenderer($catalog, $this->urls(), $cache)->render($chapter, false);
        $on      = new ManualRenderer($catalog, $this->urls(), $cache, '', new DeviceViews(true))->render($chapter, false);

        self::assertFalse($off->deviceViews);
        self::assertTrue($on->deviceViews);
        self::assertNotSame($off->html, $on->html);
    }

    private function render(string $slug, bool $admin, string $suffix = DeviceViews::DEFAULT_IMAGE_SUFFIX): RenderedManualChapter
    {
        $catalog = new ManualCatalog($this->directory);

        return new ManualRenderer($catalog, $this->urls(), new ArrayAdapter(), '', new DeviceViews(true, 768, $suffix))->render($catalog->find($slug) ?? self::fail('fixture'), $admin);
    }

    private function page(RenderedManualChapter $rendered): Crawler
    {
        return new Crawler('<div>' . $rendered->html . '</div>');
    }

    private function urls(): UrlGeneratorInterface
    {
        if (!self::$booted) {
            self::bootKernel();
        }
        $urls = self::getContainer()->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');

        return $urls;
    }
}
