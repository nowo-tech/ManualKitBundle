<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\SearchKitBundle\Model\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * 1.12: the Desktop / Phone switch, the `?device=` of a search hit, phone companions served to members, the search
 * and `nowo:manual:translate` with `device_views.enabled: true`.
 */
final class DeviceViewsTest extends WebTestCase
{
    private const array ENABLED = ['directory' => '%kernel.project_dir%/tests/Fixtures/devices/manual', 'device_views' => ['enabled' => true]];

    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['manual'] ?? []);
    }

    public function testAChapterWithDeviceContentGetsTheSwitchTheAttributesAndTheScript(): void
    {
        $page = $this->page('alice', '/en/manual/01-tasks');

        $article = $page->filter('article[data-nowo-manual-devices]');
        self::assertSame('768', $article->attr('data-nowo-manual-devices'));
        self::assertNull($article->attr('data-nowo-manual-device-initial'));
        $switch = $page->filter('[data-testid="manual-device-switch"]');
        self::assertSame(['group', 'View for', ''], [$switch->attr('role'), $switch->attr('aria-label'), $switch->attr('hidden')], 'hidden until the script shows it');
        self::assertSame(['Desktop', 'Phone'], $switch->filter('button')->each(static fn (Crawler $button): string => $button->text()));
        self::assertSame(['button', 'false', 'desktop'], [$switch->filter('button')->attr('type'), $switch->filter('button')->attr('aria-pressed'), $switch->filter('button')->attr('data-nowo-manual-device-option')]);
        self::assertSame('/bundles/nowomanualkit/js/manual-devices.js', $page->filter('script[src$="manual-devices.js"]')->attr('src'));
        self::assertCount(0, $page->filter('script:not([src])'), 'no inline script');
        self::assertSame(['desktop', 'phone'], $page->filter('.nowo-manual__toc li[data-nowo-manual-device]')->each(static fn (Crawler $item): ?string => $item->attr('data-nowo-manual-device')));
        self::assertCount(2, $page->filter('.manual-prose .manual-device'));
    }

    public function testTheSpanishPageHasTranslatedLabels(): void
    {
        $page = $this->page('alice', '/es/manual/01-tasks');

        self::assertSame(['Escritorio', 'Móvil'], $page->filter('[data-testid="manual-device-switch"] button')->each(static fn (Crawler $button): string => $button->text()));
        self::assertSame('Vista para', $page->filter('[data-testid="manual-device-switch"]')->attr('aria-label'));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function deviceParameters(): iterable
    {
        yield 'phone' => ['?device=phone', 'phone'];
        yield 'desktop' => ['?device=desktop', 'desktop'];
        yield 'unknown' => ['?device=tablet', null];
        yield 'case' => ['?device=Phone', null];
        yield 'markup' => ['?device=%22%3E%3Cscript%3E', null];
        yield 'array' => ['?device[]=phone', null];
    }

    #[DataProvider('deviceParameters')]
    public function testOnlyAllowListedDevicesOpenAView(string $query, ?string $initial): void
    {
        $page = $this->page('alice', '/en/manual/01-tasks' . $query);

        self::assertSame($initial, $page->filter('article')->attr('data-nowo-manual-device-initial'));
        self::assertStringNotContainsString('<script>', (string) $page->html());
    }

    /** FrankenPHP worker mode, kernel reset false: one request's view never reaches the next. */
    public function testAKeptAliveKernelKeepsNoDeviceStateBetweenRequests(): void
    {
        $client = self::createClient(['manual' => self::ENABLED], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $client->disableReboot();

        $views = [];
        foreach (['?device=phone', '', '?device=desktop', '?device=tablet', '?device=phone'] as $query) {
            $views[] = $client->request('GET', '/en/manual/01-tasks' . $query)->filter('article')->attr('data-nowo-manual-device-initial');
        }

        self::assertSame(['phone', null, 'desktop', null, 'phone'], $views);
    }

    public function testChaptersWithoutDeviceContentHaveNoSwitch(): void
    {
        foreach (['/en/manual/02-plain', '/en/manual'] as $uri) {
            self::ensureKernelShutdown();
            $page = $this->page('alice', $uri . '?device=phone');
            self::assertCount(0, $page->filter('[data-nowo-manual-devices], [data-nowo-manual-device-switch], script[src$="manual-devices.js"]'), $uri);
        }
    }

    public function testTheBreakpointIsConfigurable(): void
    {
        $page = $this->page('alice', '/en/manual/01-tasks', ['device_views' => ['enabled' => true, 'breakpoint' => 900]]);

        self::assertSame('900', $page->filter('article')->attr('data-nowo-manual-devices'));
    }

    public function testMembersGetThePhoneCompanionsTheirChaptersShow(): void
    {
        $client = self::createClient(['manual' => self::ENABLED], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $client->request('GET', '/en/manual/images/board.phone.png');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/en/manual/images/drawer.phone.png');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/en/manual/images/drawer.png');
        self::assertResponseStatusCodeSame(404, 'replaced by its companion inside the phone block: no chapter shows it');

        self::ensureKernelShutdown();
        $off = self::createClient(['manual' => ['directory' => self::ENABLED['directory']]], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $off->request('GET', '/en/manual/images/board.phone.png');
        self::assertResponseStatusCodeSame(404, 'device views off: no chapter shows the companion');
    }

    public function testASearchHitInADeviceSectionOpensThatView(): void
    {
        $provider = $this->searchProvider();
        $hits     = static fn (string $text): array => array_map(static fn ($item): string => $item->url, $provider->search(new SearchQuery($text, new InMemoryUser('alice', null), 10))->items ?? []);

        self::assertSame(['/en/manual/01-tasks?device=phone#filter-from-the-bottom-sheet'], $hits('sheet'));
        self::assertSame(['/en/manual/01-tasks?device=desktop#filter-from-the-sidebar'], $hits('sidebar'));
        self::assertSame(['/en/manual/01-tasks?device=desktop#filter-from-the-sidebar', '/en/manual/01-tasks?device=phone#filter-from-the-bottom-sheet'], $hits('filter'), 'both views are indexed');
        self::assertSame(['/en/manual/01-tasks#open-the-board'], $hits('kanban'), 'shared sections open the chapter as it is');
    }

    public function testTranslateKeepsTheDeviceMarkers(): void
    {
        $directory = sys_get_temp_dir() . '/manual-devices-translate-' . bin2hex(random_bytes(4));
        new Filesystem()->mirror(__DIR__ . '/../Fixtures/devices/manual', $directory);
        try {
            $config      = ['directory' => $directory, 'device_views' => ['enabled' => true], 'machine_translation' => ['provider' => 'service', 'service' => 'test.machine_translator']];
            $application = new Application(self::bootKernel(['manual' => $config]));
            $translate   = new CommandTester($application->find('nowo:manual:translate'));
            self::assertSame(Command::SUCCESS, $translate->execute(['locale' => 'es', '--chapter' => ['01-tasks'], '--force' => true]));

            $written = (string) file_get_contents($directory . '/es/01-tasks.md');
            self::assertSame(3, substr_count($written, '<!-- /device -->'));
            self::assertMatchesRegularExpression('/<!-- device: phone -->\n\S*PHONE:/', $written, 'the marker stays, the content is translated');
            self::assertStringContainsString('<!-- device: desktop -->', $written);
            self::assertStringContainsString('<!-- device: tablet -->', $written, 'every marker is kept as written');
            self::assertStringContainsString('<!-- audience: admin -->', $written);
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function page(string $user, string $uri, array $config = []): Crawler
    {
        $client = self::createClient(['manual' => $config + self::ENABLED], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
        $page   = $client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $page;
    }

    private function searchProvider(): ManualSearchProvider
    {
        self::bootKernel(['manual' => self::ENABLED]);
        $container = self::getContainer();
        $stack     = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $request = Request::create('/en/manual');
        $request->setLocale('en');
        $stack->push($request);
        $router = $container->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->getContext()->setParameter('_locale', 'en');
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale('en');
        $tokens = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('alice', null, ['ROLE_USER']), 'main', ['ROLE_USER']));
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        return $provider;
    }
}
