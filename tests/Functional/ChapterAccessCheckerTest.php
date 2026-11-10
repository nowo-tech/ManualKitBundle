<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Security\AllowAllManualChapterAccessChecker;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;
use Nowo\ManualKitBundle\Tests\Support\NoInstallChapterAccess;
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

use function array_key_exists;

/**
 * 1.9: a host ManualChapterAccessCheckerInterface hides one chapter (and its sub-pages) from navigation, previous /
 * next, sub-pages, search and the page itself; the default keeps every chapter the roles allow.
 */
final class ChapterAccessCheckerTest extends WebTestCase
{
    /**
     * @param array{checker?: class-string|null} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(['directory' => '%kernel.project_dir%/tests/Fixtures/tree/manual'], false, array_key_exists('checker', $options) ? $options['checker'] : NoInstallChapterAccess::class);
    }

    public function testTheDefaultIsAllowAllAndTheAccessPortIsAliased(): void
    {
        self::bootKernel(['checker' => null]);
        $container = self::getContainer();

        self::assertInstanceOf(ManualAccess::class, $container->get(ManualAccessInterface::class));
        self::assertInstanceOf(AllowAllManualChapterAccessChecker::class, $container->get(ManualChapterAccessCheckerInterface::class));
    }

    public function testARefusedChapterAndItsSubPagesAreHiddenAndA404(): void
    {
        $client  = $this->signedIn('alice');
        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertResponseIsSuccessful();
        self::assertSame(['README', 'guide', 'faq', 'flat-child'], $this->nav($crawler));
        self::assertSame([], $crawler->filter('[data-testid^="manual-child-"]')->each(static fn (Crawler $a): string => (string) $a->attr('data-testid')));
        self::assertSame('/en/manual', $crawler->filter('[data-testid="manual-previous"]')->attr('href'));
        self::assertSame('/en/manual/faq', $crawler->filter('[data-testid="manual-next"]')->attr('href'), 'install and docker are skipped');

        $client->request('GET', '/en/manual/guide/install');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/guide/install/docker');
        self::assertResponseStatusCodeSame(404, 'a refused parent hides its children');
        $client->request('GET', '/en/manual/install.md');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/faq/flat-child');
        self::assertResponseIsSuccessful();
    }

    public function testTheRuleAppliesToAdministratorsToo(): void
    {
        $client  = $this->signedIn('admin');
        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertResponseIsSuccessful();
        self::assertNotContains('install', $this->nav($crawler));
        self::assertContains('secrets', $this->nav($crawler));
        $client->request('GET', '/en/manual/guide/install/docker');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSearchLeavesRefusedChaptersOut(): void
    {
        $provider = $this->provider();
        $user     = new InMemoryUser('alice', null, ['ROLE_USER']);

        self::assertNull($provider->search(new SearchQuery('container', $user, 10)), 'docker sits under install');
        self::assertNull($provider->search(new SearchQuery('setup', $user, 10)));
        $group = $provider->search(new SearchQuery('handbook', $user, 10)) ?? self::fail('a match');
        self::assertSame(['/en/manual/guide'], array_map(static fn (SearchItem $item): string => $item->url, $group->items));
    }

    /**
     * @return list<string>
     */
    private function nav(Crawler $crawler): array
    {
        return $crawler->filter('[data-testid="manual-nav"] [data-testid^="manual-nav-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 11));
    }

    private function signedIn(string $user): KernelBrowser
    {
        self::ensureKernelShutdown();

        return self::createClient([], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
    }

    private function provider(): ManualSearchProvider
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $container = self::getContainer();
        $request   = Request::create('/');
        $request->setLocale('en');
        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);
        $urls = $container->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('u', null, ['ROLE_USER']), 'main', ['ROLE_USER']));
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        return $provider;
    }
}
