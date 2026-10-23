<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\TranslationState;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * 1.2: a manifest `file` serves a Markdown file of the docs root (e.g. the CHANGELOG) as a source-only chapter.
 */
final class ExternalChapterTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(['directory' => '%kernel.project_dir%/tests/Fixtures/external/manual'] + ($options['manual'] ?? []));
    }

    public function testTheChapterIsSourceOnlyWithItsOwnLinkBaseAndTocDepth(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->setContext(RequestContext::fromUri('http://localhost')->setParameter('_locale', 'en'));
        $stack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push(Request::create('/en/manual'));
        $catalog = self::getContainer()->get(ManualCatalog::class);
        self::assertInstanceOf(ManualCatalog::class, $catalog);

        $chapter = $catalog->find('changelog', 'es') ?? self::fail('external chapter');
        self::assertTrue($chapter->external);
        self::assertTrue($chapter->adminOnly);
        self::assertSame('Changelog', $chapter->title);
        self::assertSame(TranslationState::Source, $chapter->translation);
        self::assertSame('', $chapter->docsDir);
        self::assertSame(2, $chapter->tocDepth);
        self::assertStringEndsWith('/tests/Fixtures/external/CHANGES.md', $chapter->path);
        self::assertSame('manual', ($catalog->find('01-guide') ?? self::fail('guide'))->docsDir);

        $renderer = self::getContainer()->get(ManualRenderer::class);
        self::assertInstanceOf(ManualRenderer::class, $renderer);
        $rendered = $renderer->render($chapter, true);
        $page     = new Crawler('<div>' . $rendered->html . '</div>');
        self::assertSame('https://github.com/acme/app/blob/main/docs/UPGRADE.md#to-110', $page->filter('a:contains("UPGRADE")')->attr('href'));
        self::assertSame('/en/manual/01-guide#start', $page->filter('a:contains("guide")')->attr('href'));
        self::assertSame('https://github.com/acme/app/blob/main/docs/ops/RUN.md', $page->filter('a:contains("ops")')->attr('href'));
        self::assertSame(['1.1.0 - 2026-10-23', '1.0.0 - 2026-10-01'], array_map(static fn (array $row): string => str_replace(['[', ']'], '', $row['text']), $rendered->toc));

        $audit = self::getContainer()->get(TranslationAudit::class);
        self::assertInstanceOf(TranslationAudit::class, $audit);
        self::assertNotContains('changelog', array_column($audit->run(), 'slug'));
        self::assertFalse($audit->stamp('changelog', 'es'));
    }

    public function testMachineTranslationSkipsIt(): void
    {
        $kernel = self::bootKernel(['manual' => ['machine_translation' => ['provider' => 'libretranslate', 'url' => 'http://libretranslate.test']]]);
        $tester = new CommandTester(new Application($kernel)->find('nowo:manual:translate'));

        $tester->execute(['locale' => 'es', '--dry-run' => true]);
        self::assertStringContainsString('01-guide', $tester->getDisplay());
        self::assertStringNotContainsString('changelog', $tester->getDisplay());
    }
}
