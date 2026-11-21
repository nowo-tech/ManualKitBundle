<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\AudiencesTestKernel;
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
use function in_array;
use function sort;
use function substr;

/**
 * 1.13: four named audiences (user by default, admin, developer, customer) and a customer portal root, behind two real
 * firewalls. Every reader sees only the chapters and blocks of their audiences, in the page, the navigation, the
 * "On this page" list, previous / next, links, images and search; and no chapter crosses from one root to the other.
 */
final class NamedAudiencesTest extends WebTestCase
{
    /** Default-root chapters by reader (manual order). */
    private const array DEFAULT_CHAPTERS = [
        'uma'     => ['README', 'using', 'everyone', 'staff-typo'],
        'ada'     => ['README', 'using', 'admin-guide', 'shared', 'everyone', 'staff-typo'],
        'dev'     => ['README', 'using', 'dev', 'dev-child', 'shared', 'everyone', 'staff-typo'],
        'both'    => ['README', 'using', 'admin-guide', 'dev', 'dev-child', 'shared', 'everyone', 'staff-typo'],
        'insider' => ['README', 'using', 'everyone', 'staff-typo'],
    ];

    /** Paths of every default-root chapter but the home page. */
    private const array DEFAULT_PATHS = [
        'using'       => '/en/manual/using',
        'admin-guide' => '/en/manual/admin-guide',
        'dev'         => '/en/manual/dev',
        'dev-child'   => '/en/manual/dev/dev-child',
        'shared'      => '/en/manual/shared',
        'everyone'    => '/en/manual/everyone',
        'staff-typo'  => '/en/manual/staff-typo',
    ];

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new AudiencesTestKernel();
    }

    public function testEachStaffReaderSeesOnlyTheirChaptersInTheNavigationAndPages(): void
    {
        foreach (self::DEFAULT_CHAPTERS as $user => $chapters) {
            $client  = $this->signedIn($user);
            $crawler = $client->request('GET', '/en/manual');
            self::assertResponseIsSuccessful($user);
            self::assertSame(self::sorted($chapters), self::sorted($this->nav($crawler)), $user . ': navigation');

            foreach (self::DEFAULT_PATHS as $slug => $path) {
                $client->request('GET', $path);
                self::assertResponseStatusCodeSame(in_array($slug, $chapters, true) ? 200 : 404, $user . ' ' . $path);
            }
            foreach (['portal-home', 'portal-guide', 'portal-faq', 'portal-nested'] as $portal) {
                $client->request('GET', '/en/manual/' . $portal);
                self::assertResponseStatusCodeSame(404, $user . ': a portal chapter is never served in the default root');
                $client->request('GET', '/en/manual/' . $portal . '.md');
                self::assertResponseStatusCodeSame(404, $user . ': nor through the .md alias');
            }
        }
    }

    public function testPreviousAndNextFollowTheReadersChapters(): void
    {
        foreach (self::DEFAULT_CHAPTERS as $user => $chapters) {
            $client = $this->signedIn($user);
            $paths  = array_map(static fn (string $slug): string => $slug === 'README' ? '/en/manual' : self::DEFAULT_PATHS[$slug], $chapters);
            foreach ($paths as $index => $path) {
                $slug             = $chapters[$index] ?? '';
                $crawler          = $client->request('GET', $path);
                $previous         = $crawler->filter('[data-testid="manual-previous"]');
                $next             = $crawler->filter('[data-testid="manual-next"]');
                $expectedPrevious = $paths[$index - 1] ?? null;
                $expectedNext     = $paths[$index + 1] ?? null;
                self::assertSame($expectedPrevious, $previous->count() === 0 ? null : $previous->attr('href'), $user . ' ' . $slug . ': previous');
                self::assertSame($expectedNext, $next->count() === 0 ? null : $next->attr('href'), $user . ' ' . $slug . ': next');
            }
        }
    }

    public function testBlocksAndTheTableOfContentsFollowTheReader(): void
    {
        $blocks = [
            'uma'     => [],
            'ada'     => ['ADMIN-BLOCK-text', 'ADMIN-OR-DEV-BLOCK-text'],
            'dev'     => ['DEVELOPER-ONLY-text', 'ADMIN-OR-DEV-BLOCK-text'],
            'both'    => ['ADMIN-BLOCK-text', 'DEVELOPER-ONLY-text', 'ADMIN-OR-DEV-BLOCK-text'],
            'insider' => [],
        ];
        $toc = [
            'uma'     => ['Daily tasks'],
            'ada'     => ['Daily tasks', 'Organisation settings'],
            'dev'     => ['Daily tasks', 'API keys'],
            'both'    => ['Daily tasks', 'Organisation settings', 'API keys'],
            'insider' => ['Daily tasks'],
        ];
        foreach ($blocks as $user => $shown) {
            $crawler = $this->signedIn($user)->request('GET', '/en/manual/using');
            $html    = (string) $crawler->filter('.manual-prose')->html();
            self::assertStringContainsString('USER-TEXT-everyone-in-the-default-root', $html);
            foreach (['ADMIN-BLOCK-text', 'DEVELOPER-ONLY-text', 'ADMIN-OR-DEV-BLOCK-text'] as $block) {
                in_array($block, $shown, true) ? self::assertStringContainsString($block, $html, $user) : self::assertStringNotContainsString($block, $html, $user . ': ' . $block);
            }
            self::assertStringNotContainsString('CUSTOMER-BLOCK-IN-DEFAULT-ROOT', $html, $user . ': customer blocks belong to the portal root');
            self::assertStringNotContainsString('GITHUB-ONLY-text', $html);
            self::assertSame($toc[$user], $crawler->filter('[data-testid="manual-toc"] a')->each(static fn (Crawler $a): string => $a->text()), $user . ': table of contents');
        }
    }

    public function testLinksToChaptersTheReaderCannotOpenArePlainText(): void
    {
        $expected = [
            'uma'  => ['/en/manual/everyone'],
            'ada'  => ['/en/manual/admin-guide', '/en/manual/everyone'],
            'dev'  => ['/en/manual/dev', '/en/manual/everyone'],
            'both' => ['/en/manual/admin-guide', '/en/manual/dev', '/en/manual/everyone'],
        ];
        foreach ($expected as $user => $links) {
            $crawler = $this->signedIn($user)->request('GET', '/en/manual/using');
            self::assertSame($links, $crawler->filter('.manual-prose a:not(.manual-anchor):not(.manual-zoom)')->each(static fn (Crawler $a): string => (string) $a->attr('href')), $user);
            $text = $crawler->filter('.manual-prose')->text();
            self::assertStringContainsString('Portal guide', $text, 'the link text stays, as plain text');
            self::assertStringNotContainsString('portal-guide', (string) $crawler->filter('.manual-prose')->html(), $user . ': no route to a portal chapter');
        }
    }

    public function testImagesAreServedOnlyWhenTheReadersChaptersShowThem(): void
    {
        $images = [
            'uma'  => ['user.png' => 200, 'admin.png' => 404, 'dev.png' => 404, 'customer.png' => 404, 'secret.png' => 404],
            'ada'  => ['user.png' => 200, 'admin.png' => 200, 'dev.png' => 404, 'customer.png' => 404, 'secret.png' => 404],
            'dev'  => ['user.png' => 200, 'admin.png' => 404, 'dev.png' => 200, 'customer.png' => 404, 'secret.png' => 404],
            'both' => ['user.png' => 200, 'admin.png' => 200, 'dev.png' => 200, 'customer.png' => 404, 'secret.png' => 404, 'staff.png' => 404],
        ];
        foreach ($images as $user => $statuses) {
            $client = $this->signedIn($user);
            foreach ($statuses as $file => $status) {
                $client->request('GET', '/en/manual/images/' . $file);
                self::assertResponseStatusCodeSame($status, $user . ' ' . $file);
            }
        }
    }

    public function testThePortalServesOnlyCustomerChaptersUnderItsOwnPath(): void
    {
        $client  = $this->contact('carla');
        $crawler = $client->request('GET', '/portal/help');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('PORTAL-HOME-text', (string) $crawler->filter('.manual-prose')->html());
        self::assertSame(['everyone', 'portal-faq', 'portal-guide', 'portal-home', 'portal-nested'], self::sorted($this->nav($crawler)));
        self::assertSame('/portal/help', $crawler->filter('[data-testid="manual-nav-portal-home"]')->attr('href'));
        self::assertSame(['/portal/help/portal-guide', '/portal/help/everyone'], $crawler->filter('.manual-prose a')->each(static fn (Crawler $a): string => (string) $a->attr('href')), 'staff chapter: plain text');
        self::assertSame('/portal/help/portal-guide', $crawler->filter('[data-testid="manual-next"]')->attr('href'));

        $crawler = $client->request('GET', '/portal/help/portal-guide');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->filter('.manual-prose')->html();
        self::assertStringContainsString('CUSTOMER-ONLY-text', $html);
        self::assertStringNotContainsString('STAFF-NOTE-IN-PORTAL', $html, 'a user block is not of this root');
        self::assertStringNotContainsString('admin-guide', $html);
        self::assertSame(['Paying invoices'], $crawler->filter('[data-testid="manual-toc"] a')->each(static fn (Crawler $a): string => $a->text()));
        self::assertSame('/portal/help', $crawler->filter('[data-testid="manual-previous"]')->attr('href'));
        self::assertSame('/portal/help/everyone', $crawler->filter('[data-testid="manual-next"]')->attr('href'));
        self::assertSame('/portal/help/images/customer.png', $crawler->filter('.manual-prose img')->attr('src'));

        $client->request('GET', '/portal/help/everyone');
        self::assertResponseIsSuccessful('a chapter of both roots');
        $client->request('GET', '/portal/help/portal-home');
        self::assertResponseStatusCodeSame(404, 'the home page is only the index');
        foreach (['README', 'using', 'admin-guide', 'dev', 'dev/dev-child', 'shared', 'using.md'] as $staff) {
            $client->request('GET', '/portal/help/' . $staff);
            self::assertResponseStatusCodeSame(404, $staff . ' is a default-root chapter');
        }
        $client->request('GET', '/portal/help/images/customer.png');
        self::assertResponseIsSuccessful();
        foreach (['user.png', 'admin.png', 'dev.png', 'staff.png', 'secret.png'] as $file) {
            $client->request('GET', '/portal/help/images/' . $file);
            self::assertResponseStatusCodeSame(404, $file . ' is only shown in staff chapters and blocks');
        }
    }

    /** Audit MK113-H1: a stray or nested marker never lets staff text out to a portal reader. */
    public function testStrayAndNestedMarkersNeverLeakStaffBlocksToThePortal(): void
    {
        $client  = $this->contact('carla');
        $crawler = $client->request('GET', '/portal/help/portal-faq');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->filter('.manual-prose')->html();
        self::assertStringContainsString('FAQ-START', $html);
        self::assertStringNotContainsString('STAFF-ESCALATION', $html, 'an unclosed user block');
        self::assertStringNotContainsString('staff.png', $html);
        self::assertStringNotContainsString('FAQ-AFTER-TYPO', $html, 'the chapter is cut at the stray marker');
        self::assertSame([], $crawler->filter('[data-testid="manual-toc"] a')->each(static fn (Crawler $a): string => $a->text()));

        $crawler = $client->request('GET', '/portal/help/portal-nested');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->filter('.manual-prose')->html();
        self::assertStringContainsString('NESTED-CUSTOMER', $html);
        self::assertStringNotContainsString('STAFF-NESTED', $html, 'a user block nested in a customer block');
        self::assertStringNotContainsString('NESTED-TAIL', $html);
        self::assertSame([], $crawler->filter('[data-testid="manual-toc"] a')->each(static fn (Crawler $a): string => $a->text()));

        $client->request('GET', '/portal/help/images/staff.png');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->search('/portal/search', 'carla', 'escalation', 'portal'));
        self::assertSame([], $this->search('/portal/search', 'carla', 'internals', 'portal'));
    }

    /** Audit MK113-H1, the other way round: a stray customer marker in a staff chapter, for a reader of every staff audience. */
    public function testAStrayCustomerMarkerNeverLeaksToStaff(): void
    {
        $client  = $this->signedIn('both');
        $crawler = $client->request('GET', '/en/manual/staff-typo');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->filter('.manual-prose')->html();
        self::assertStringContainsString('STAFF-START', $html);
        self::assertStringNotContainsString('CUSTOMER-SECRET', $html);
        self::assertStringNotContainsString('STAFF-AFTER-TYPO', $html);
        self::assertSame([], $crawler->filter('[data-testid="manual-toc"] a')->each(static fn (Crawler $a): string => $a->text()));
        $client->request('GET', '/en/manual/images/secret.png');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->search('/en/search', 'both', 'secrets'));
    }

    public function testThePortalNeedsItsRole(): void
    {
        self::ensureKernelShutdown();
        $anonymous = self::createClient();
        $anonymous->request('GET', '/portal/help');
        self::assertResponseStatusCodeSame(401);

        $this->contact('noperm')->request('GET', '/portal/help/portal-guide');
        self::assertResponseStatusCodeSame(403);
        $this->contact('uma')->request('GET', '/portal/help');
        self::assertResponseStatusCodeSame(403, 'ROLE_USER does not open the portal root');
    }

    public function testARootIsNeverServedInAnotherRootsFirewall(): void
    {
        $carla = $this->contact('carla');
        foreach (['/portal/misplaced', '/portal/misplaced/everyone', '/portal/misplaced/using', '/portal/misplaced/images/user.png'] as $path) {
            $carla->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path . ': the default root inside the portal firewall');
        }
        foreach (['insider', 'both'] as $user) {
            $client = $this->signedIn($user);
            foreach (['/en/misplaced-portal', '/en/misplaced-portal/portal-guide', '/en/misplaced-portal/everyone', '/en/misplaced-portal/images/customer.png'] as $path) {
                $client->request('GET', $path);
                self::assertResponseStatusCodeSame(404, $user . ' ' . $path . ': the portal root inside the main firewall');
            }
        }
    }

    public function testSearchAnswersForTheReaderOfTheRequestsRoot(): void
    {
        // Default root (main firewall).
        self::assertSame(['/en/manual/everyone'], $this->search('/en/search', 'uma', 'contact'));
        self::assertSame([], $this->search('/en/search', 'uma', 'api'));
        self::assertSame([], $this->search('/en/search', 'uma', 'keys'), 'a developer block section stays out');
        self::assertSame(['/en/manual/dev', '/en/manual/using#api-keys', '/en/manual/dev#rest-api'], $this->search('/en/search', 'dev', 'api'));
        self::assertSame(['/en/manual/using#api-keys'], $this->search('/en/search', 'dev', 'keys'));
        self::assertSame(['/en/manual/shared', '/en/manual/shared#access-tokens'], $this->search('/en/search', 'ada', 'tokens'));
        self::assertSame([], $this->search('/en/search', 'uma', 'tokens'));
        self::assertSame([], $this->search('/en/search', 'insider', 'invoices'), 'portal chapters never in the default root');
        self::assertSame([], $this->search('/en/search', 'insider', 'customer'), 'nor portal blocks');

        // Portal root (portal firewall).
        self::assertSame(['/portal/help/portal-guide', '/portal/help/portal-guide#paying-invoices'], $this->search('/portal/search', 'carla', 'invoices', 'portal'));
        self::assertSame(['/portal/help/everyone'], $this->search('/portal/search', 'carla', 'contact', 'portal'));
        self::assertSame([], $this->search('/portal/search', 'carla', 'start', 'portal'), 'staff chapters never in the portal');
        self::assertSame([], $this->search('/portal/search', 'carla', 'staff', 'portal'), 'nor staff blocks');
        self::assertSame([], $this->search('/portal/search', 'noperm', 'invoices', 'portal'), 'no reader of the root: no group');
    }

    /**
     * @return list<string> the URLs of the hits
     */
    private function search(string $path, string $user, string $text, string $firewall = 'main'): array
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $container = self::getContainer();
        $request   = Request::create($path);
        $request->setLocale('en');
        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);
        $urls = $container->get(UrlGeneratorInterface::class);
        self::assertInstanceOf(UrlGeneratorInterface::class, $urls);
        $urls->getContext()->setParameter('_locale', 'en');
        $roles  = $firewall === 'main' ? AudiencesTestKernel::STAFF[$user] : AudiencesTestKernel::CONTACTS[$user];
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser($user, null, $roles), $firewall, $roles));
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);
        $group = $provider->search(new SearchQuery($text, new InMemoryUser($user, null, $roles), 20));

        return $group === null ? [] : array_map(static fn (SearchItem $item): string => $item->url, $group->items);
    }

    /**
     * @return list<string>
     */
    private function nav(Crawler $crawler): array
    {
        return $crawler->filter('[data-testid="manual-nav"] [data-testid^="manual-nav-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 11));
    }

    /**
     * @param list<string> $list
     *
     * @return list<string>
     */
    private static function sorted(array $list): array
    {
        sort($list);

        return $list;
    }

    private function signedIn(string $user): KernelBrowser
    {
        self::ensureKernelShutdown();

        return self::createClient([], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
    }

    private function contact(string $user): KernelBrowser
    {
        return $this->signedIn($user);
    }
}
