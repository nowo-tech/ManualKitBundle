<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\TranslationState;
use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\ManualKitBundle\Twig\ManualLinkExtension;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
 * 1.6: sub-pages — a three-level tree (guide → install → docker), an admin branch, a child kept at the manual root.
 */
final class ChapterTreeTest extends WebTestCase
{
    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(($options['manual'] ?? []) + ['directory' => '%kernel.project_dir%/tests/Fixtures/tree/manual']);
    }

    public function testTheCatalogWalksTheTreeDepthFirstAndInheritsAccess(): void
    {
        $catalog = $this->catalog();

        self::assertSame([], $catalog->problems());
        self::assertSame(
            ['README', 'guide', 'install', 'docker', 'secrets', 'faq', 'flat-child', 'ops', 'backup'],
            array_map(static fn (ManualChapter $c): string => $c->slug, $catalog->all()),
        );
        $docker = $catalog->find('docker') ?? self::fail('docker');
        self::assertSame(['guide', 'install'], $docker->ancestors);
        self::assertSame(['Guide', 'Install'], $docker->ancestorTitles);
        self::assertSame('install', $docker->parent());
        self::assertSame(3, $docker->depth());
        self::assertSame('guide/install/docker', $docker->urlPath());
        self::assertSame('Guide › Install › Docker', $docker->trailTitle());
        self::assertSame('guide/install', $docker->folder);
        self::assertSame('manual/guide/install', $docker->docsDir);
        self::assertSame('using', $docker->group, 'the group of the top-level chapter');
        self::assertSame('', ($catalog->find('flat-child') ?? self::fail('flat child'))->folder, 'a child may stay at the root');
        self::assertSame('', ($catalog->find('README') ?? self::fail('home'))->urlPath());

        $backup = $catalog->find('backup') ?? self::fail('backup');
        self::assertTrue($backup->adminOnly, 'a child never widens its parent audience');
        self::assertSame('administration', $backup->group);
        self::assertSame(['README', 'guide', 'install', 'docker', 'faq', 'flat-child'], array_map(static fn (ManualChapter $c): string => $c->slug, $catalog->visible(false)));

        $guide = $catalog->find('guide') ?? self::fail('guide');
        self::assertSame(['install'], array_map(static fn (ManualChapter $c): string => $c->slug, $catalog->children($guide, false)));
        self::assertSame(['install', 'secrets'], array_map(static fn (ManualChapter $c): string => $c->slug, $catalog->children($guide, true)));
        self::assertSame(['guide', 'install'], array_map(static fn (ManualChapter $c): string => $c->slug, $catalog->ancestors($docker)));

        [$previous, $next] = $catalog->neighbours($docker, false);
        self::assertSame(['install', 'faq'], [$previous?->slug, $next?->slug], 'previous / next walk the tree, admin pages skipped');
    }

    public function testAMemberReadsTheTreeWithItsNavigationBreadcrumbAndSubPages(): void
    {
        $client  = $this->signedIn('alice');
        $crawler = $client->request('GET', '/en/manual/guide/install/docker');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Docker');

        $nav = $crawler->filter('[data-testid="manual-nav"]');
        self::assertSame(['README', 'guide', 'install', 'docker', 'faq', 'flat-child'], $nav->filter('[data-testid^="manual-nav-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 11)));
        self::assertSame('/en/manual/guide/install/docker', $nav->filter('[data-testid="manual-nav-docker"]')->attr('href'));
        self::assertSame('page', $nav->filter('[data-testid="manual-nav-docker"]')->attr('aria-current'));
        self::assertNotNull($crawler->filter('[data-testid="manual-branch-guide"]')->attr('open'), 'the current branch is open');
        self::assertNotNull($crawler->filter('[data-testid="manual-branch-install"]')->attr('open'));
        self::assertNull($crawler->filter('[data-testid="manual-branch-faq"]')->attr('open'), 'other branches are folded');
        self::assertSame('Sub-pages of Guide', trim($crawler->filter('[data-testid="manual-branch-guide"] > summary')->text()));

        $crumbs = $crawler->filter('[data-testid="manual-breadcrumbs"] li');
        self::assertSame(['Manual', 'Guide', 'Install', 'Docker'], $crumbs->each(static fn (Crawler $li): string => trim(str_replace('/', '', $li->text()))));
        self::assertSame(['/en/manual', '/en/manual/guide', '/en/manual/guide/install'], $crawler->filter('[data-testid="manual-breadcrumbs"] a')->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame('page', $crawler->filter('[data-testid="manual-breadcrumbs"] [aria-current]')->attr('aria-current'));

        self::assertSame('/en/manual/guide/install', $crawler->filter('[data-testid="manual-previous"]')->attr('href'));
        self::assertSame('/en/manual/faq', $crawler->filter('[data-testid="manual-next"]')->attr('href'));

        $prose = $crawler->filter('.manual-prose');
        self::assertSame('/en/manual/guide/install', $prose->filter('a:contains("Install")')->attr('href'), '../install.md');
        self::assertSame('/en/manual/faq', $prose->filter('a:contains("FAQ")')->attr('href'), '../../faq.md');
        self::assertSame('/en/manual/guide/install/docker', $prose->filter('a:contains("sibling-less")')->attr('href'));
        self::assertSame('/en/manual/images/shot.png', $prose->filter('img')->attr('src'), '../../images');
        self::assertSelectorExists('h2#run');

        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertSame(['install'], $crawler->filter('[data-testid^="manual-child-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 13)), 'parent pages list the sub-pages the reader may open');
        self::assertSelectorNotExists('[data-testid="manual-breadcrumbs"]');
        $prose = $crawler->filter('.manual-prose');
        self::assertSame('/en/manual/guide/install', $prose->filter('a:contains("Install")')->attr('href'));
        self::assertSame('/en/manual/guide/install#requirements', $prose->filter('a:contains("short form")')->attr('href'), 'child.md from the parent');
        self::assertCount(0, $prose->filter('a:contains("Secrets")'), 'a link to a page the reader cannot open is plain text');
        self::assertStringContainsString('Secrets', $prose->text());

        $crawler = $client->request('GET', '/en/manual/guide/install');
        self::assertSame(['docker'], $crawler->filter('[data-testid^="manual-child-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 13)));
        self::assertSame('/en/manual/guide', $crawler->filter('.manual-prose a:contains("guide")')->attr('href'));
        self::assertSame('/en/manual/guide/install/docker#run', $crawler->filter('.manual-prose a:contains("Docker")')->attr('href'));
        self::assertSame('/en/manual/faq', $crawler->filter('.manual-prose a:contains("bare form")')->attr('href'), 'a bare slug.md from a sub-folder');

        $crawler = $client->request('GET', '/en/manual/faq/flat-child');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/en/manual/faq');
        self::assertSame('/en/manual/faq/flat-child', $client->getCrawler()->filter('.manual-prose a')->attr('href'));
    }

    public function testAccessIsInheritedAndEveryOtherPathIsA404(): void
    {
        $client = $this->signedIn('alice');
        foreach (['/en/manual/guide/secrets', '/en/manual/ops', '/en/manual/ops/backup', '/en/manual/ops/backup.md', '/en/manual/secrets', '/en/manual/guide/docker', '/en/manual/faq/install', '/en/manual/install/docker', '/en/manual/x/install/docker', '/en/manual/guide/install/nope', '/en/manual/guide/install/docker/deeper'] as $uri) {
            $client->request('GET', $uri);
            self::assertResponseStatusCodeSame(404, $uri);
        }

        $client->request('GET', '/en/manual/docker');
        self::assertResponseRedirects('/en/manual/guide/install/docker', 301, 'a slug-only link reaches the nested chapter');
        $client->request('GET', '/en/manual/guide/install.md');
        self::assertResponseRedirects('/en/manual/guide/install', 301);
        $client->request('GET', '/en/manual/guide/install/docker.md');
        self::assertResponseRedirects('/en/manual/guide/install/docker', 301);
        $client->request('GET', '/en/manual/docker.md');
        self::assertResponseRedirects('/en/manual/guide/install/docker', 301);

        $admin = $this->signedIn('admin');
        $admin->request('GET', '/en/manual/ops/backup');
        self::assertResponseIsSuccessful();
        $crawler = $admin->request('GET', '/en/manual/guide');
        self::assertSame('/en/manual/guide/secrets', $crawler->filter('.manual-prose a:contains("Secrets")')->attr('href'));
        self::assertSame(['install', 'secrets'], $crawler->filter('[data-testid^="manual-child-"]')->each(static fn (Crawler $a): string => substr((string) $a->attr('data-testid'), 13)));
    }

    public function testTranslationsLiveInTheSameSubFolders(): void
    {
        $client  = $this->signedIn('alice');
        $crawler = $client->request('GET', '/es/manual/guide/install');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Instalar');
        self::assertSelectorExists('[data-testid="manual-translation-notice"]');
        self::assertSame('/es/manual/guide', $crawler->filter('.manual-prose a:contains("guía")')->attr('href'), 'link written from the translation folder');
        self::assertSame('/es/manual/guide/install/docker#run', $crawler->filter('.manual-prose a:contains("Docker")')->attr('href'));
        $crawler = $client->request('GET', '/es/manual/guide/install/docker');
        self::assertSame(['Manual', 'Guide', 'Instalar'], $crawler->filter('[data-testid="manual-breadcrumbs"] a')->each(static fn (Crawler $a): string => $a->text()));

        $catalog = $this->catalog();
        self::assertSame(TranslationState::Outdated, ($catalog->find('install', 'es') ?? self::fail('install'))->translation);
        self::assertSame(TranslationState::Missing, ($catalog->find('docker', 'es') ?? self::fail('docker'))->translation);
        self::assertStringEndsWith('/tree/manual/guide/install.md', $catalog->sourcePath('install', 'guide'));
        self::assertStringEndsWith('/tree/manual/es/guide/install.md', $catalog->translationPath('install', 'es', 'guide'));

        $audit = self::getContainer()->get(TranslationAudit::class);
        self::assertInstanceOf(TranslationAudit::class, $audit);
        $rows = [];
        foreach ($audit->run() as $row) {
            $rows[$row['slug']] = [$row['state'], $row['missing_anchors']];
        }
        self::assertSame([TranslationState::Outdated, ['requirements']], $rows['install'], 'the anchor check covers the tree');
        self::assertSame([TranslationState::Missing, []], $rows['docker']);
    }

    public function testStampAndTranslateWriteIntoTheSubFolder(): void
    {
        $directory = sys_get_temp_dir() . '/nowo-manual-tree-' . bin2hex(random_bytes(4));
        (new Filesystem())->mirror(__DIR__ . '/../Fixtures/tree/manual', $directory);
        try {
            self::bootKernel(['manual' => ['directory' => $directory, 'machine_translation' => ['provider' => 'libretranslate', 'url' => 'https://translate.example.test']]]);
            $application = new Application(self::$kernel ?? self::fail('kernel'));

            $stamp = new CommandTester($application->find('nowo:manual:stamp'));
            self::assertSame(0, $stamp->execute(['locale' => 'es', 'chapter' => 'install']));
            self::assertStringContainsString('translation-of: ' . ManualCatalog::sourceHash((string) file_get_contents($directory . '/guide/install.md')), (string) file_get_contents($directory . '/es/guide/install.md'));

            $translate = new CommandTester($application->find('nowo:manual:translate'));
            self::assertSame(0, $translate->execute(['locale' => 'es', '--chapter' => ['docker']]));
            self::assertFileExists($directory . '/es/guide/install/docker.md');
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    public function testSearchIndexesSubPagesWithTheirParentsAndAccess(): void
    {
        $member = $this->provider(['ROLE_USER']);
        $user   = new InMemoryUser('alice', null, ['ROLE_USER']);

        $group = $member->search(new SearchQuery('container', $user, 10)) ?? self::fail('a match');
        self::assertSame([['Guide › Install › Docker', '/en/manual/guide/install/docker', 'Manual · Using the application']], $this->rows($group->items), 'own tags, "Parent › Child"');
        $group = $member->search(new SearchQuery('prerequisites', $user, 10)) ?? self::fail('a section');
        self::assertSame([['Requirements', '/en/manual/guide/install#requirements', 'Guide › Install']], $this->rows($group->items));
        self::assertNull($member->search(new SearchQuery('backup', $user, 10)), 'a child of an admin page stays hidden');
        self::assertNull($member->search(new SearchQuery('secrets', $user, 10)));

        $admin = $this->provider(['ROLE_ADMIN', 'ROLE_USER']);
        self::assertSame([['Operations › Backup', '/en/manual/ops/backup', 'Manual · Administration']], $this->rows(($admin->search(new SearchQuery('backup', $user, 10)) ?? self::fail('admin match'))->items));
    }

    public function testManualHrefKnowsTheTree(): void
    {
        $this->catalog();
        $links = self::getContainer()->get(ManualLinkExtension::class);
        self::assertInstanceOf(ManualLinkExtension::class, $links);

        self::assertSame('/en/manual/guide/install/docker#run', $links->href('manual/guide/install/docker.md#run'));
        self::assertSame('/en/manual/guide/install/docker', $links->href('manual/docker.md'), 'the slug alone');
        self::assertSame('/en/manual/faq/flat-child', $links->href('manual/flat-child.md'));
        self::assertTrue($links->isExternal('manual/ops/docker.md'), 'a folder that is not the chapter one');
        self::assertSame('/en/manual', $links->chapterPath($this->catalog()->find('README') ?? self::fail('home')));
    }

    private function catalog(): ManualCatalog
    {
        if (!self::$booted) {
            self::bootKernel();
        }
        $this->router()->getContext()->setParameter('_locale', 'en');
        $catalog = self::getContainer()->get(ManualCatalog::class);
        self::assertInstanceOf(ManualCatalog::class, $catalog);

        return $catalog;
    }

    private function router(): RouterInterface
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }

    private function signedIn(string $user): KernelBrowser
    {
        self::ensureKernelShutdown();

        return self::createClient([], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
    }

    /**
     * @param list<string> $roles
     */
    private function provider(array $roles): ManualSearchProvider
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $container = self::getContainer();
        $request   = Request::create('/');
        $request->setLocale('en');
        $requests = $container->get(RequestStack::class);
        self::assertInstanceOf(RequestStack::class, $requests);
        $requests->push($request);
        $this->router()->getContext()->setParameter('_locale', 'en');
        $translator = $container->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale('en');
        $tokens = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('u', null, $roles), 'main', $roles));
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
