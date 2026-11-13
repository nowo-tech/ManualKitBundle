<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Tests\Support\HostManualCatalog;
use Nowo\ManualKitBundle\Tests\Support\HostManualRenderer;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * REQ-ARCH-001 rule 1 (1.10): the catalog and the renderer are ports aliased to the bundle services; a host replaces
 * either one by aliasing the interface to its own service.
 */
final class HostPortsTest extends WebTestCase
{
    /**
     * @param array{catalog?: class-string|null, renderer?: class-string|null} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(
            ['directory' => '%kernel.project_dir%/tests/Fixtures/tree/manual'],
            false,
            null,
            null,
            $options['catalog'] ?? null,
            $options['renderer'] ?? null,
        );
    }

    public function testTheDefaultsAreTheBundleServices(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(ManualCatalog::class, $container->get(ManualCatalogInterface::class));
        self::assertInstanceOf(ManualRenderer::class, $container->get(ManualRendererInterface::class));
        self::assertSame($container->get(ManualCatalog::class), $container->get(ManualCatalogInterface::class));
        self::assertSame($container->get(ManualRenderer::class), $container->get(ManualRendererInterface::class));
    }

    public function testAHostCatalogReachesTheControllerAndTheNavigation(): void
    {
        self::ensureKernelShutdown();
        $client  = self::createClient(['catalog' => HostManualCatalog::class], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertResponseIsSuccessful();
        self::assertNotContains('faq', $this->nav($crawler));

        $client->request('GET', '/en/manual/faq');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAHostRendererReachesTheChapterPage(): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient(['renderer' => HostManualRenderer::class], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $client->request('GET', '/en/manual/guide');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('host-rendered guide', (string) $client->getResponse()->getContent());
    }

    /**
     * @return list<string>
     */
    private function nav(Crawler $crawler): array
    {
        return $crawler->filter('[data-testid="manual-nav"] [data-testid^="manual-nav-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 11));
    }
}
