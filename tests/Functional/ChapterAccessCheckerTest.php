<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Security\AllowAllManualChapterAccessChecker;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;
use Nowo\ManualKitBundle\Tests\Support\NoInstallChapterAccess;
use Nowo\ManualKitBundle\Tests\Support\PermissiveAccessPolicy;
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
     * @param array{checker?: class-string|null, policy?: class-string|null, manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(
            $options['manual'] ?? ['directory' => '%kernel.project_dir%/tests/Fixtures/tree/manual'],
            false,
            array_key_exists('checker', $options) ? $options['checker'] : NoInstallChapterAccess::class,
            $options['policy'] ?? null,
        );
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

    public function testImagesOfRefusedChaptersAreA404ForEveryone(): void
    {
        // shot.png is only shown in install and docker, both refused by the host rule.
        $this->signedIn('alice')->request('GET', '/en/manual/images/shot.png');
        self::assertResponseStatusCodeSame(404);
        $this->signedIn('admin')->request('GET', '/en/manual/images/shot.png');
        self::assertResponseStatusCodeSame(404, 'admins too once the host rule refuses a chapter');
    }

    public function testWithTheDefaultRuleImagesAreServedAsBefore(): void
    {
        $this->signedIn('alice', ['checker' => null])->request('GET', '/en/manual/images/shot.png');
        self::assertResponseIsSuccessful();
        $this->signedIn('admin', ['checker' => null])->request('GET', '/en/manual/images/shot.png');
        self::assertResponseIsSuccessful();
    }

    public function testAReplacedAccessPolicyCannotOpenAdminOnlyChapters(): void
    {
        $options = ['checker' => null, 'policy' => PermissiveAccessPolicy::class, 'manual' => []];
        $client  = $this->signedIn('alice', $options);
        $client->request('GET', '/en/manual/01-using');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/en/manual/02-admin');
        self::assertResponseStatusCodeSame(404, 'the admin-only rule is enforced by the bundle');
        $client->request('GET', '/en/manual/02-admin.md');
        self::assertResponseStatusCodeSame(404);

        $this->signedIn('admin', $options)->request('GET', '/en/manual/02-admin');
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

    /**
     * @param array<string, mixed> $options
     */
    private function signedIn(string $user, array $options = []): KernelBrowser
    {
        self::ensureKernelShutdown();

        return self::createClient($options, ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
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
