<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ManualRendererTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/manual';

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel();
    }

    public function testRendersAFixtureChapterWithStableAnchorsAndLocaleRoutes(): void
    {
        $this->urls();
        $renderer = $this->service(ManualRenderer::class);
        $catalog  = $this->service(ManualCatalog::class);

        $chapter = $catalog->find('01-using') ?? self::fail('fixture');
        $member  = $renderer->render($chapter, false);
        $page    = new Crawler('<div>' . $member->html . '</div>');
        self::assertSame('filters', $page->filter('h2')->attr('id'), 'the stable anchor wins over the title slug');
        self::assertSame('#filters', $page->filter('h2 a.manual-anchor')->attr('href'));
        self::assertCount(0, $page->filter('p > a[id]'), 'the anchor paragraph is gone');
        self::assertSame('/en/manual', $page->filter('a:contains("the README")')->attr('href'), '../README.md from a translation folder style link');
        self::assertCount(0, $page->filter('a[href*="02-admin"]'));
        self::assertSame('<kbd>K</kbd>', $page->filter('kbd')->outerHtml());
        self::assertSame([
            ['level' => 2, 'id' => 'filters', 'text' => 'Filter the list', 'tags' => ['search', 'buscar']],
            ['level' => 3, 'id' => 'keyboard', 'text' => 'Keyboard', 'tags' => []],
        ], $member->toc);

        $admin = new Crawler('<div>' . $renderer->render($chapter, true)->html . '</div>');
        self::assertSame('/en/manual/02-admin#users', $admin->filter('a:contains("admin chapter")')->attr('href'));

        $adminChapter = $catalog->find('02-admin') ?? self::fail('fixture');
        self::assertSame('users', new Crawler('<div>' . $renderer->render($adminChapter, true)->html . '</div>')->filter('h2')->attr('id'), 'anchor inside the heading');

        $spanish = $catalog->find('README', 'es') ?? self::fail('fixture');
        self::assertStringContainsString('href="/en/manual/01-using#filters"', $renderer->render($spanish, false)->html, '../01-using.md from es/');
    }

    public function testAudienceBlocksRepoLinksFiguresAndSanitising(): void
    {
        $directory = sys_get_temp_dir() . '/manual-render-' . bin2hex(random_bytes(4));
        mkdir($directory . '/images', recursive: true);
        try {
            file_put_contents($directory . '/manifest.yaml', "chapters:\n  - { slug: README }\n  - { slug: 01-member }\n  - { slug: 02-admin, audience: admin }\n");
            file_put_contents($directory . '/README.md', "# Home\n\nSee [member](01-member.md#usage).\n");
            file_put_contents($directory . '/02-admin.md', "# Admin\n");
            copy(self::FIXTURES . '/images/shot.png', $directory . '/images/shot.png');
            copy(self::FIXTURES . '/images/phone.png', $directory . '/images/phone.png');
            copy(self::FIXTURES . '/images/logo.svg', $directory . '/images/logo.svg');
            file_put_contents($directory . '/01-member.md', <<<'MD'
                # Member

                ## Usage
                <!-- tags: how to, uso, , Verwendung -->

                Back [home](README.md), the [admin chapter](02-admin.md#x), [production](../PRODUCTION.md#tls),
                [folder](../ops/), [web](https://example.com), [top](#usage), [empty]() and `code`.

                <!-- audience: admin -->
                Admin-only paragraph.
                <!-- /audience -->

                ### **Details** `x`

                ![Shot](images/shot.png "The account card") ![Remote](https://example.com/a.png){.manual-img--small width=200}

                ![Phone](./images/phone.png)

                ![Logo](images/logo.svg) ![Gone](images/missing.png)

                Text with an inline ![icon](https://example.com/i.png) image.

                <a id="dangling"></a> not before a heading, <a id="named">a named link</a>.

                <script>alert(1)</script>
                MD);

            $catalog  = new ManualCatalog($directory);
            $urls     = $this->urls();
            $renderer = new ManualRenderer($catalog, $urls, new ArrayAdapter(), 'https://github.com/x/docs/');
            $chapter  = $catalog->find('01-member') ?? self::fail('chapter');

            $member = $renderer->render($chapter, false);
            $page   = new Crawler('<div>' . $member->html . '</div>');
            self::assertStringNotContainsString('<h1', $member->html, 'the title is the page heading');
            self::assertStringNotContainsString('Admin-only paragraph', $member->html);
            self::assertStringNotContainsString('audience', $member->html);
            self::assertStringNotContainsString('<script', $member->html);
            self::assertStringContainsString('admin chapter', $member->html, 'the text stays');
            self::assertCount(0, $page->filter('a[href*="02-admin"]'));
            self::assertSame('/en/manual', $page->filter('a:contains("home")')->attr('href'));
            self::assertSame('https://github.com/x/docs/PRODUCTION.md#tls', $page->filter('a:contains("production")')->attr('href'));
            self::assertSame('https://github.com/x/docs/ops/', $page->filter('a:contains("folder")')->attr('href'));
            self::assertSame('_blank', $page->filter('a:contains("production")')->attr('target'));
            self::assertSame('noopener noreferrer', $page->filter('a:contains("web")')->attr('rel'));
            self::assertSame('#usage', $page->filter('a:contains("top")')->attr('href'));
            $image = $page->filter('img[alt="Shot"]');
            self::assertSame('/en/manual/images/shot.png', $image->attr('src'));
            self::assertSame(['40', '20', 'lazy'], [$image->attr('width'), $image->attr('height'), $image->attr('loading')]);
            self::assertSame('https://example.com/a.png', $page->filter('img[alt="Remote"]')->attr('src'));
            self::assertCount(2, $page->filter('div.manual-gallery')->first()->filter('figure.manual-figure'), 'images of one paragraph side by side');
            self::assertSame('The account card', $page->filter('figure:has(img[alt="Shot"]) figcaption')->text(), 'the title is the caption');
            self::assertNull($image->attr('title'));
            self::assertSame('/en/manual/images/shot.png', $page->filter('figure:has(img[alt="Shot"]) a.manual-zoom')->attr('href'));
            self::assertSame(['manual-img--small', '200'], [$page->filter('img[alt="Remote"]')->attr('class'), $page->filter('img[alt="Remote"]')->attr('width')], 'CommonMark attributes');
            self::assertCount(0, $page->filter('figure:has(img[alt="icon"])'), 'an image inside text stays inline');
            self::assertSame('manual-figure manual-figure--portrait', $page->filter('figure:has(img[alt="Phone"])')->attr('class'), 'phone screenshots are sized down');
            self::assertSame(ManualRenderer::PORTRAIT, $page->filter('img[alt="Phone"]')->attr('class'));
            self::assertNull($page->filter('img[alt="Logo"]')->attr('width'), 'no intrinsic size for an SVG');
            self::assertSame('/en/manual/images/missing.png', $page->filter('img[alt="Gone"]')->attr('src'));
            self::assertSame('dangling', $page->filter('a#dangling')->attr('id'), 'an anchor not before a heading stays');
            self::assertSame('a named link', $page->filter('a#named')->text(), 'an anchor with text is a link, not a heading id');
            self::assertSame([
                ['level' => 2, 'id' => 'usage', 'text' => 'Usage', 'tags' => ['how to', 'uso', 'Verwendung']],
                ['level' => 3, 'id' => 'details-x', 'text' => 'Details x', 'tags' => []],
            ], $member->toc);
            self::assertStringNotContainsString('Verwendung', $member->html, 'section tags stay out of the page');
            self::assertEquals($member, $renderer->render($chapter, false), 'cached');

            $admin = $renderer->render($chapter, true);
            self::assertStringContainsString('Admin-only paragraph', $admin->html);
            self::assertSame('/en/manual/02-admin#x', new Crawler('<div>' . $admin->html . '</div>')->filter('a:contains("admin chapter")')->attr('href'));

            $home = $catalog->find('README') ?? self::fail('home');
            self::assertSame('<p>See <a href="/en/manual/01-member#usage">member</a>.</p>', trim($renderer->render($home, false)->html), 'no DOM pass without images or anchors');
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testAudienceFilterKeepsOrDropsTheBlock(): void
    {
        $markdown = "a\n<!-- audience: admin -->\nb\n<!--/audience-->\nc\n<!-- audience: github -->\nd\n<!-- /audience -->\ne";

        self::assertSame("a\n\nb\n\nc\n\ne", ManualRenderer::audienceFiltered($markdown, true));
        self::assertSame("a\n\nc\n\ne", ManualRenderer::audienceFiltered($markdown, false), 'github blocks never show in the app');
    }

    public function testAudienceFilterFailsClosedOnAStrayMarker(): void
    {
        $unclosed = "a\n<!-- audience: admin -->\nsecret";
        self::assertSame("a\n", ManualRenderer::audienceFiltered($unclosed, false), 'an unclosed block hides the rest');
        self::assertSame("a\n\nsecret", ManualRenderer::audienceFiltered($unclosed, true));

        $typo = "a\n<!-- audience admin -->\nsecret\n<!-- /audience -->\nb";
        self::assertSame("a\n", ManualRenderer::audienceFiltered($typo, false));

        self::assertSame("a\n\nb", ManualRenderer::audienceFiltered("a\n<!-- Audience: Admin -->\nsecret\n<!-- /AUDIENCE -->\nb", false), 'case-insensitive');
        self::assertSame("a\n\nsecret\n\nb", ManualRenderer::audienceFiltered("a\n<!-- Audience: Admin -->\nsecret\n<!-- /AUDIENCE -->\nb", true));
        self::assertSame("a\n", ManualRenderer::audienceFiltered("a\n<!-- audiences: admin -->\nsecret", false), 'a misspelt marker still cuts');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        if (!self::$booted) {
            self::bootKernel();
        }
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->service(UrlGeneratorInterface::class);
        $urls->getContext()->setParameter('_locale', 'en');

        return $urls;
    }
}
