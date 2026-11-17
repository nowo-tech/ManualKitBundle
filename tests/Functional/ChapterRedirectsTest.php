<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function array_map;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.11: chapter redirects — 301 to the new chapter in the request locale from every chapter route, one hop for
 * chains, the access check on the target before the redirect (no existence oracle), and the anchor map of the page.
 */
final class ChapterRedirectsTest extends WebTestCase
{
    public const array CONFIG = [
        'directory' => '%kernel.project_dir%/tests/Fixtures/redirects/manual',
        'redirects' => ['07-inline' => 'faq'],
    ];

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(self::CONFIG);
    }

    public function testAMovedChapterAnswers301ToItsNewPlaceInTheRequestLocale(): void
    {
        $client = $this->signedIn('alice');

        $this->assertMoved($client, '/en/manual/01-install', '/en/manual/setup');
        $this->assertMoved($client, '/es/manual/01-install', '/es/manual/setup');
        $this->assertMoved($client, '/en/manual/07-inline', '/en/manual/faq', 'inline config map');
        $this->assertMoved($client, '/en/manual/05-chain', '/en/manual/setup', 'a chain is served in one hop');
        $this->assertMoved($client, '/en/manual/06-merged', '/en/manual/faq#legacy', 'a chapter merged into a section');

        $crawler = $client->request('GET', '/es/manual/setup');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Instalación');
        self::assertNull($crawler->filter('article')->attr('data-nowo-manual-anchors'), 'no map on a plain page');
        self::assertSelectorNotExists('script[src="/bundles/nowomanualkit/js/manual-anchors.js"]');
    }

    public function testMarkdownAndChildRoutesRedirectTheSameWay(): void
    {
        $client = $this->signedIn('alice');

        $this->assertMoved($client, '/en/manual/01-install.md', '/en/manual/setup');
        $this->assertMoved($client, '/en/manual/02-deploy-old', '/en/manual/setup/deploy', 'to a nested chapter');
        $this->assertMoved($client, '/es/manual/anything/02-deploy-old', '/es/manual/setup/deploy');
        $this->assertMoved($client, '/en/manual/a/b/02-deploy-old', '/en/manual/setup/deploy');
        $this->assertMoved($client, '/en/manual/a/02-deploy-old.md', '/en/manual/setup/deploy');
        $this->assertMoved($client, '/en/manual/a/b/02-deploy-old.md', '/en/manual/setup/deploy');
        $this->assertMoved($client, '/en/manual/01-install/deploy', '/en/manual/setup/deploy', 'a parent that moved');
        $this->assertMoved($client, '/en/manual/01-install/deploy.md', '/en/manual/setup/deploy');
        $this->assertMoved($client, '/en/manual/x/01-install/deploy', '/en/manual/setup/deploy');

        $client->request('GET', '/en/manual/faq/deploy');
        self::assertResponseStatusCodeSame(404, 'a wrong parent that did not move stays a 404');
        $client->request('GET', '/en/manual/99-nope');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/99-nope.md');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/x/99-nope');
        self::assertResponseStatusCodeSame(404);
    }

    public function testARedirectToAChapterTheReaderMayNotOpenAnswersLikeThatChapter(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/manual/03-old-admin');
        self::assertResponseStatusCodeSame(401, 'anonymous: asked to sign in, as for any chapter');

        $client = $this->signedIn('alice');
        $client->request('GET', '/en/manual/ops');
        self::assertResponseStatusCodeSame(404);
        $target = (string) $client->getResponse()->getContent();
        $client->request('GET', '/en/manual/03-old-admin');
        self::assertResponseStatusCodeSame(404, 'the same 404 as the target');
        self::assertSame($target, (string) $client->getResponse()->getContent(), 'byte for byte');
        $client->request('GET', '/en/manual/03-old-admin.md');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/x/03-old-admin');
        self::assertResponseStatusCodeSame(404);

        $this->assertMoved($this->signedIn('admin'), '/en/manual/03-old-admin', '/en/manual/ops');
    }

    public function testAnOldChapterWithMovedAnchorsLandsWithTheMarkerAndTheAnchorMap(): void
    {
        $client = $this->signedIn('alice');
        $this->assertMoved($client, '/es/manual/04-split', '/es/manual/setup?moved_from=04-split');

        $crawler = $client->request('GET', '/es/manual/setup?moved_from=04-split');
        self::assertResponseIsSuccessful();
        self::assertSame(['moved' => ['docker' => '/es/manual/setup/deploy#docker', 'intro' => '/es/manual/setup#overview'], 'own' => []], $this->anchors($crawler));
        self::assertSelectorExists('script[src="/bundles/nowomanualkit/js/manual-anchors.js"][defer]');
        self::assertSelectorNotExists('article script', 'no inline script');

        $crawler = $client->request('GET', '/en/manual/setup?moved_from=01-install');
        self::assertNull($crawler->filter('article')->attr('data-nowo-manual-anchors'), 'a marker without moved anchors is ignored');
        $crawler = $client->request('GET', '/en/manual/setup?moved_from=../evil');
        self::assertResponseIsSuccessful();
        self::assertNull($crawler->filter('article')->attr('data-nowo-manual-anchors'));
        $crawler = $client->request('GET', '/en/manual/setup?moved_from[]=04-split');
        self::assertResponseIsSuccessful();
        self::assertNull($crawler->filter('article')->attr('data-nowo-manual-anchors'));
    }

    public function testOwnAnchorsSkipTargetsAndSectionsTheReaderCannotSee(): void
    {
        $member  = $this->signedIn('alice');
        $crawler = $member->request('GET', '/en/manual/faq');
        self::assertSame(['moved' => [], 'own' => ['old-question' => '/en/manual/faq#questions']], $this->anchors($crawler), 'the admin section id is not revealed, the reader stays put');
        $crawler = $member->request('GET', '/en/manual/setup');
        self::assertNull($crawler->filter('article')->attr('data-nowo-manual-anchors'), 'the admin chapter target is left out');

        $admin   = $this->signedIn('admin');
        $crawler = $admin->request('GET', '/en/manual/faq');
        self::assertSame(['moved' => [], 'own' => ['old-internal' => '/en/manual/faq#internal', 'old-question' => '/en/manual/faq#questions']], $this->anchors($crawler));
        $crawler = $admin->request('GET', '/en/manual/setup');
        self::assertSame(['moved' => [], 'own' => ['gone' => '/en/manual/ops#secret']], $this->anchors($crawler));
    }

    public function testSearchServesOnlyTheNewSlugs(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $catalog   = $container->get(ManualCatalogInterface::class);
        self::assertInstanceOf(ManualCatalogInterface::class, $catalog);
        self::assertSame(['README', 'setup', 'deploy', 'faq', 'ops'], array_map(static fn (ManualChapter $chapter): string => $chapter->slug, $catalog->all()));

        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push(Request::create('/'));
        $urls = $container->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN', 'ROLE_USER']), 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        $user  = new InMemoryUser('admin', null);
        $group = $provider->search(new SearchQuery('install', $user, 10)) ?? self::fail('the setup chapter by its tag');
        self::assertSame(['/en/manual/setup'], array_map(static fn (SearchItem $item): string => $item->url, $group->items));
        $group = $provider->search(new SearchQuery('docker', $user, 10)) ?? self::fail('a section');
        self::assertSame(['/en/manual/setup/deploy#docker'], array_map(static fn (SearchItem $item): string => $item->url, $group->items));
    }

    private function assertMoved(KernelBrowser $client, string $from, string $to, string $message = ''): void
    {
        $client->request('GET', $from);
        self::assertResponseStatusCodeSame(301, $message . ' ' . $from);
        self::assertResponseHeaderSame('Location', $to, $message . ' ' . $from);
    }

    /**
     * @return array<mixed>
     */
    private function anchors(Crawler $crawler): array
    {
        $raw = (string) $crawler->filter('article')->attr('data-nowo-manual-anchors');
        $map = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        self::assertIsArray($map);

        return $map;
    }

    private function signedIn(string $user): KernelBrowser
    {
        self::ensureKernelShutdown();

        return self::createClient([], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
    }
}
