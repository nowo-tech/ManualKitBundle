<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class ManualSearchProviderTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel();
    }

    public function testFindsChaptersAndSectionsByTitleAndTagsForTheReader(): void
    {
        $provider = $this->provider(['ROLE_USER'], 'es');
        $user     = new InMemoryUser('alice', null, ['ROLE_USER']);

        $group = $provider->search(new SearchQuery('inicio', $user, 10)) ?? self::fail('a match');
        self::assertSame(['manual', 'Manual', 1], [$group->key, $group->label, $group->total]);
        self::assertSame([['Usar la aplicación', '/en/manual/01-using', 'Manual · Usar la aplicación']], $this->rows($group->items), 'chapter tags');

        self::assertNull($provider->search(new SearchQuery('buscar', $user, 10)), 'the Spanish section carries no tags');
    }

    public function testSectionsFollowChaptersAndTheLimitApplies(): void
    {
        $provider = $this->provider(['ROLE_USER'], 'en');
        $user     = new InMemoryUser('alice', null, ['ROLE_USER']);

        $group = $provider->search(new SearchQuery('buscar', $user, 10)) ?? self::fail('a match');
        self::assertSame([['Filter the list', '/en/manual/01-using#filters', 'Using the app']], $this->rows($group->items));
        self::assertSame(1, $provider->search(new SearchQuery('manual', $user, 10))?->total, 'the home title');
        self::assertNull($provider->search(new SearchQuery('users', $user, 10)), 'admin sections stay hidden');
        self::assertCount(1, $this->provider(['ROLE_ADMIN', 'ROLE_USER'], 'en')->search(new SearchQuery('users', $user, 1))->items ?? []);
    }

    public function testNothingForVisitorsWhoCannotReadTheManual(): void
    {
        self::assertNull($this->provider([], 'en')->search(new SearchQuery('manual', new InMemoryUser('x', null), 10)));
    }

    /**
     * @param list<string> $roles
     */
    private function provider(array $roles, string $locale): ManualSearchProvider
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $container = self::getContainer();
        $request   = Request::create('/');
        $request->setLocale($locale);
        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);
        $urls = $container->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale($locale);
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        if ($roles !== []) {
            $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('u', null, $roles), 'main', $roles));
        }
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        return $provider;
    }

    /**
     * @param list<SearchItem> $items
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function rows(array $items): array
    {
        return array_map(static fn (SearchItem $item): array => [$item->title, $item->url, $item->subtitle], $items);
    }
}
