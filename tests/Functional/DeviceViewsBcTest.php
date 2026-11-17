<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\SearchKitBundle\Model\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Translation\LocaleAwareInterface;

use function dirname;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * 1.12 BC: with `device_views.enabled: false` (the default) a manual that holds device blocks and phone companion
 * images renders, and searches, byte for byte what 1.11.0 did. The golden files under tests/Fixtures/bc-1.11.0 were
 * written by this test on the v1.11.0 code (`MANUAL_BC_WRITE=1`, the "default" rows).
 */
final class DeviceViewsBcTest extends WebTestCase
{
    private const string GOLDEN = __DIR__ . '/../Fixtures/bc-1.11.0';

    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['manual'] ?? []);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function configs(): iterable
    {
        $directory = ['directory' => '%kernel.project_dir%/tests/Fixtures/devices/manual'];

        yield 'default' => [$directory];
        yield 'explicit false' => [[...$directory, 'device_views' => ['enabled' => false, 'breakpoint' => 900, 'image_suffix' => '.mobile']]];
        yield 'explicit false only' => [[...$directory, 'device_views' => ['enabled' => false]]];
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function pages(): iterable
    {
        foreach (self::configs() as $name => [$config]) {
            yield $name . ': member chapter' => [$config, 'alice', '/en/manual/01-tasks'];
            yield $name . ': admin chapter' => [$config, 'admin', '/en/manual/01-tasks'];
            yield $name . ': device param ignored' => [$config, 'alice', '/en/manual/01-tasks?device=phone'];
            yield $name . ': es chapter' => [$config, 'alice', '/es/manual/01-tasks'];
            yield $name . ': plain chapter' => [$config, 'alice', '/en/manual/02-plain'];
            yield $name . ': home' => [$config, 'alice', '/en/manual'];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('pages')]
    public function testDisabledDeviceViewsRenderAsIn1110(array $config, string $user, string $uri): void
    {
        $client = self::createClient(['manual' => $config], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
        $client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        $this->assertGolden($user . str_replace(['/', '?', '='], '_', $uri) . '.html', (string) $client->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('configs')]
    public function testDisabledDeviceViewsSearchAsIn1110(array $config): void
    {
        self::bootKernel(['manual' => $config]);
        $container = self::getContainer();
        $stack     = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $request = Request::create('/en/manual');
        $request->setLocale('en');
        $request->attributes->set('_locale', 'en');
        $stack->push($request);
        $router = $container->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->getContext()->setParameter('_locale', 'en');
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale('en');
        $tokens = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN', 'ROLE_USER']), 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $provider = $container->get(ManualSearchProvider::class);
        self::assertInstanceOf(ManualSearchProvider::class, $provider);

        $rows = [];
        foreach (['filter', 'sheet', 'sidebar', 'kanban', 'board', 'done'] as $text) {
            foreach ($provider->search(new SearchQuery($text, new InMemoryUser('admin', null), 10))->items ?? [] as $item) {
                $rows[] = [$text, $item->title, $item->url, $item->subtitle];
            }
        }

        $this->assertGolden('search.json', (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertGolden(string $name, string $actual): void
    {
        $actual = str_replace(dirname(__DIR__, 2) . '/tests/', '%root%/tests/', $actual);
        $file   = self::GOLDEN . '/' . $name;
        if (getenv('MANUAL_BC_WRITE') === '1') {
            @mkdir(self::GOLDEN, 0o775, true);
            file_put_contents($file, $actual);
        }
        self::assertFileExists($file);
        self::assertSame((string) file_get_contents($file), $actual, $name);
    }
}
