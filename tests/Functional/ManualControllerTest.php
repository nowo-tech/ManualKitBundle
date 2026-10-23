<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

final class ManualControllerTest extends WebTestCase
{
    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['manual'] ?? []);
    }

    public function testAnonymousVisitorsAreAskedToSignIn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/manual');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAMemberReadsTheHomeAndTheChaptersButNotTheAdminOnes(): void
    {
        $client = $this->signedIn('alice');

        $crawler = $client->request('GET', '/en/manual');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Manual');
        self::assertSelectorTextSame('title', 'Manual');
        self::assertSame(['README', '01-using'], $crawler->filter('[data-testid^="manual-nav-"]')->each(static fn ($a): string => substr((string) $a->attr('data-testid'), 11)));
        self::assertSame('page', $crawler->filter('[data-testid="manual-nav-README"]')->attr('aria-current'));
        self::assertSame('/bundles/nowomanualkit/css/manual.css', $crawler->filter('link[rel="stylesheet"]')->attr('href'));
        self::assertSelectorNotExists('[data-testid="manual-translation-notice"]');
        self::assertSelectorNotExists('[data-testid="manual-previous"]');
        self::assertSame('/en/manual/01-using', $crawler->filter('[data-testid="manual-next"]')->attr('href'));

        $crawler = $client->request('GET', '/en/manual/01-using');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Using the app');
        self::assertSame('/en/manual', $crawler->filter('[data-testid="manual-previous"]')->attr('href'));
        self::assertSelectorNotExists('[data-testid="manual-next"]');
        self::assertSelectorExists('[data-testid="manual-toc"] a[href="#filters"]');
        self::assertSelectorExists('[data-testid="manual-lightbox"]');

        $client->request('GET', '/en/manual/02-admin');
        self::assertResponseStatusCodeSame(404, 'no existence oracle');
        $client->request('GET', '/en/manual/README');
        self::assertResponseStatusCodeSame(404, 'the home lives at the index');
        $client->request('GET', '/en/manual/99-nope');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnAdministratorReadsTheAdminChapters(): void
    {
        $client  = $this->signedIn('admin');
        $crawler = $client->request('GET', '/en/manual/01-using');

        self::assertSame('/en/manual/02-admin', $crawler->filter('[data-testid="manual-next"]')->attr('href'));
        $client->request('GET', '/en/manual/02-admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Administration');
    }

    public function testTheSpanishManualShowsTranslationsAndSaysWhenOneIsMissingOrOutdated(): void
    {
        $client = $this->signedIn('admin');

        $crawler = $client->request('GET', '/es/manual');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="manual-translation-notice"]');
        self::assertSame('Inicio del manual', trim($crawler->filter('[data-testid="manual-nav-README"]')->text()));
        self::assertSelectorTextContains('.manual-prose', 'Empieza por');

        $client->request('GET', '/es/manual/01-using');
        self::assertSelectorTextSame('h1', 'Usar la aplicación');
        self::assertSelectorExists('.nowo-manual__notice--outdated');

        $client->request('GET', '/es/manual/02-admin');
        self::assertSelectorTextSame('h1', 'Administration');
        self::assertSelectorTextContains('.nowo-manual__notice--missing', 'aún no está traducido');
    }

    public function testMarkdownLinksRedirectToTheChapter(): void
    {
        $client = $this->signedIn('alice');

        $client->request('GET', '/en/manual/01-using.md');
        self::assertResponseRedirects('/en/manual/01-using', 301);
        $client->request('GET', '/en/manual/README.md');
        self::assertResponseRedirects('/en/manual', 301);
        $client->request('GET', '/en/manual/99-nope.md');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/02-admin.md');
        self::assertResponseStatusCodeSame(404, 'no existence oracle for admin chapters');
    }

    public function testImagesAreServedSandboxed(): void
    {
        $client = $this->signedIn('alice');

        $client->request('GET', '/en/manual/images/logo.svg');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $client->request('GET', '/en/manual/images/missing.png');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/images/shot.png');
        self::assertResponseIsSuccessful('shown in a member chapter');
        $client->request('GET', '/en/manual/images/phone.png');
        self::assertResponseStatusCodeSame(404, 'only the admin chapter shows it');

        $client->setServerParameter('PHP_AUTH_USER', 'admin');
        $client->request('GET', '/en/manual/images/phone.png');
        self::assertResponseIsSuccessful();
    }

    public function testAnAdminOnlyHomeIsHiddenFromMembers(): void
    {
        $directory = sys_get_temp_dir() . '/manual-admin-home-' . bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory . '/manifest.yaml', "chapters:\n  - { slug: README, audience: admin }\n");
        file_put_contents($directory . '/README.md', "# Home\n");
        try {
            $client = self::createClient(['manual' => ['directory' => $directory]], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
            $client->request('GET', '/en/manual');
            self::assertResponseStatusCodeSame(404);
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testAPublicManualAndACustomTemplate(): void
    {
        $client = self::createClient(['manual' => ['role' => '', 'template' => '@NowoManualKitBundle/layout.html.twig']]);

        $client->request('GET', '/en/manual');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="manual"]', 'the configured template renders');
    }

    private function signedIn(string $user): KernelBrowser
    {
        return self::createClient([], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
    }
}
