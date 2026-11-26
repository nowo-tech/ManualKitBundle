<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearch;
use Nowo\ManualKitBundle\Search\ManualSearchHit;
use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchTerm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Translation\LocaleAwareInterface;

use function array_map;

/**
 * 1.15 (Beacon spec 240 FR-037b): synonyms in the manual search — SearchKit's term alternatives in the header group,
 * the optional ManualSynonymsInterface in the manual's own search box — with the access rules unchanged: a synonym finds
 * a section, never a chapter or a block the reader may not open.
 */
final class ManualSearchSynonymsTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel();
    }

    public function testTheHeaderGroupMatchesTheTermAlternatives(): void
    {
        $user = new InMemoryUser('alice', null, ['ROLE_USER']);
        $this->reader(['ROLE_USER'], 'en');
        $provider = $this->provider();

        self::assertNull($provider->search(new SearchQuery('find', $user, 10)), 'no synonyms from the engine: the typed text only');
        $group = $provider->search(new SearchQuery('find', $user, 10, null, false, null, [], 'en', [new SearchTerm('search', 'en', true)])) ?? self::fail('a synonym match');
        self::assertSame([['Filter the list', '/en/manual/01-using#filters']], $this->rows($group->items));
        self::assertSame(1, $group->total);
    }

    public function testASynonymNeverOpensAChapterOrSectionTheReaderMayNotRead(): void
    {
        $user  = new InMemoryUser('alice', null, ['ROLE_USER']);
        $terms = [new SearchTerm('users', 'en', true), new SearchTerm('administration', 'en', true)];
        $this->reader(['ROLE_USER'], 'en');
        self::assertNull($this->provider()->search(new SearchQuery('people', $user, 10, null, false, null, [], 'en', $terms)), 'admin chapter and its sections stay hidden');
        self::assertNull($this->search()->search('people', 10));
        self::assertNull($this->search()->search('settings', 10));

        $this->reader(['ROLE_ADMIN', 'ROLE_USER'], 'en');
        $group = $this->provider()->search(new SearchQuery('people', $user, 10, null, false, null, [], 'en', $terms)) ?? self::fail('the admin reader');
        self::assertSame([['Administration', '/en/manual/02-admin'], ['Users', '/en/manual/02-admin#users']], $this->rows($group->items));
        self::assertSame(['Users'], $this->titles($this->search()->search('people', 10)->hits ?? []));
    }

    public function testTheOwnSearchBoxUsesTheSynonymPortInTheReadersLocale(): void
    {
        $this->reader(['ROLE_USER'], 'en');
        $search = $this->search();

        $result = $search->search('  find ', 10) ?? self::fail('a synonym match');
        self::assertSame(['Filter the list'], $this->titles($result->hits));
        self::assertTrue($result->hits[0]->section);
        self::assertSame('tabler:section', $result->hits[0]->icon);
        self::assertSame(['Using the app'], $this->titles($search->search('begin', 10)->hits ?? []), 'chapter tags through a synonym');
        self::assertNull($search->search('comienzo', 10), 'the Spanish synonym is not asked for an English reader');
        self::assertNull($search->search('   ', 10));
        self::assertSame(1, $search->find(['nothing', 'start'], 10)?->total, 'find() matches any of the given texts');
        self::assertNull($search->find([], 10));

        $this->reader(['ROLE_USER'], 'es');
        self::assertSame(['Usar la aplicación'], $this->titles($this->search()->search('comienzo', 10)->hits ?? []));
    }

    public function testNothingWithoutAReader(): void
    {
        $this->reader([], 'en');

        self::assertNull($this->search()->search('find', 10));
    }

    /**
     * FrankenPHP worker mode, kernel reset off: one ManualSearch and one provider serve alternating readers and locales
     * with the same answers every round — nothing of one search is kept for the next.
     */
    public function testOneInstanceServesAlternatingReadersInAKeptAliveKernel(): void
    {
        $this->reader(['ROLE_USER'], 'en');
        $search   = $this->search();
        $provider = $this->provider();
        $user     = new InMemoryUser('alice', null, ['ROLE_USER']);
        $terms    = [new SearchTerm('users', 'en', true)];
        $rounds   = [];
        for ($round = 0; $round < 2; ++$round) {
            $answers = [];
            foreach ([[['ROLE_ADMIN', 'ROLE_USER'], 'en'], [['ROLE_USER'], 'en'], [['ROLE_USER'], 'es'], [[], 'en']] as [$roles, $locale]) {
                $this->switchReader($roles, $locale);
                $answers[] = [
                    $this->titles($search->search('people', 10)->hits ?? []),
                    $this->titles($search->search('find', 10)->hits ?? []),
                    array_map(static fn (SearchItem $item): string => $item->title, $provider->search(new SearchQuery('people', $user, 10, null, false, null, [], $locale, $terms))->items ?? []),
                ];
            }
            $rounds[] = $answers;
        }

        self::assertSame([
            [['Users'], ['Filter the list'], ['Users']],
            [[], ['Filter the list'], []],
            [[], [], []],
            [[], [], []],
        ], $rounds[0]);
        self::assertSame($rounds[0], $rounds[1]);
    }

    /**
     * Boots a fresh kernel for one reader.
     *
     * @param list<string> $roles
     */
    private function reader(array $roles, string $locale): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $urls = self::getContainer()->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');
        $this->switchReader($roles, $locale);
    }

    /**
     * Changes the reader of the running kernel (a new request, token and locale), as a worker does between requests.
     *
     * @param list<string> $roles
     */
    private function switchReader(array $roles, string $locale): void
    {
        $container = self::getContainer();
        $requests  = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        while ($requests->pop() instanceof Request) {
        }
        $request = Request::create('/');
        $request->setLocale($locale);
        $requests->push($request);
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale($locale);
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken($roles === [] ? null : new UsernamePasswordToken(new InMemoryUser('u', null, $roles), 'main', $roles));
    }

    private function search(): ManualSearch
    {
        $search = self::getContainer()->get('test.manual_search');
        self::assertInstanceOf(ManualSearch::class, $search);

        return $search;
    }

    private function provider(): ManualSearchProvider
    {
        $provider = self::getContainer()->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        return $provider;
    }

    /**
     * @param list<SearchItem> $items
     *
     * @return list<array{0: string, 1: string}>
     */
    private function rows(array $items): array
    {
        return array_map(static fn (SearchItem $item): array => [$item->title, $item->url], $items);
    }

    /**
     * @param list<ManualSearchHit> $hits
     *
     * @return list<string>
     */
    private function titles(array $hits): array
    {
        return array_map(static fn (ManualSearchHit $hit): string => $hit->title, $hits);
    }
}
