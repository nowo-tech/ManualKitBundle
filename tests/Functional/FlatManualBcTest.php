<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
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
 * 1.6 BC: a manifest without `parent` renders byte for byte what 1.5.1 rendered. The golden files under
 * tests/Fixtures/bc-1.5.1 were written by this test on the v1.5.1 code (`MANUAL_BC_WRITE=1`).
 */
final class FlatManualBcTest extends WebTestCase
{
    private const string GOLDEN = __DIR__ . '/../Fixtures/bc-1.5.1';

    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['manual'] ?? []);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function pages(): iterable
    {
        yield 'member home' => ['alice', '/en/manual', null];
        yield 'member chapter' => ['alice', '/en/manual/01-using', null];
        yield 'member es chapter' => ['alice', '/es/manual/01-using', null];
        yield 'admin chapter' => ['admin', '/en/manual/02-admin', null];
        yield 'admin es home' => ['admin', '/es/manual', null];
        yield 'external changelog' => ['admin', '/en/manual/changelog', 'external'];
        yield 'external guide' => ['alice', '/en/manual/01-guide', 'external'];
    }

    #[DataProvider('pages')]
    public function testTheFlatManualRendersAsIn151(string $user, string $uri, ?string $fixture): void
    {
        $manual = $fixture === null ? [] : ['directory' => '%kernel.project_dir%/tests/Fixtures/' . $fixture . '/manual'];
        $client = self::createClient(['manual' => $manual], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
        $client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        $this->assertGolden($user . str_replace('/', '_', $uri) . '.html', (string) $client->getResponse()->getContent());
    }

    public function testTheFlatSearchAndProblemsAreAsIn151(): void
    {
        self::bootKernel();
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
        $catalog = $container->get(ManualCatalog::class);
        self::assertInstanceOf(ManualCatalog::class, $catalog);

        $rows = [];
        foreach (['manual', 'buscar', 'users', 'inicio', 'admin'] as $text) {
            foreach ($provider->search(new SearchQuery($text, new InMemoryUser('admin', null), 10))->items ?? [] as $item) {
                $rows[] = [$text, $item->title, $item->url, $item->subtitle];
            }
        }
        $chapters = [];
        foreach ($catalog->all('es') as $chapter) {
            $chapters[] = [$chapter->slug, $chapter->group, $chapter->adminOnly, $chapter->title, $chapter->locale, $chapter->translation->value, $chapter->docsDir, $chapter->tocDepth];
        }

        $this->assertGolden('search-and-catalog.json', (string) json_encode(['search' => $rows, 'catalog' => $chapters, 'problems' => $catalog->problems()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
