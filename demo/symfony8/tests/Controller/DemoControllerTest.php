<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Smoke tests for the NowoManualKitBundle demo.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoControllerTest extends WebTestCase
{
    public function testRootRedirectsToEnglish(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/en');
    }

    public function testGuestsAreSentToTheSignInPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Manual Kit Bundle');

        $client->request('GET', '/en/manual');
        self::assertResponseRedirects('/en/login');
    }

    public function testSignInOpensTheManual(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/en/login');
        self::assertSelectorExists('input[name="_csrf_token"]');

        $client->submit($crawler->selectButton('Sign in')->form(['_username' => 'demo', '_password' => 'demo']));
        self::assertResponseRedirects('/en/manual');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Demo manual');
        self::assertSelectorTextContains('nav.navbar', 'demo');
        self::assertSelectorExists('link[href="/bundles/nowomanualkit/css/manual.css"]');
    }

    public function testMembersReadTheirChaptersAndNeverTheAdminOne(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('demo', 'demo', ['ROLE_USER']));

        $crawler = $client->request('GET', '/en/manual/01-getting-started');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h2#first-steps');
        self::assertSelectorExists('figure.manual-figure a.manual-zoom img[src="/en/manual/images/dashboard.svg"]');
        self::assertCount(0, $crawler->filter('a[href*="02-admin"]'));

        $client->request('GET', '/en/manual/02-admin');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdministratorsReadTheAdminChapter(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $client->request('GET', '/en/manual/01-getting-started');
        self::assertSame('/en/manual/02-admin#users', $crawler->filter('.manual-prose a:contains("Administration")')->attr('href'));
        $client->request('GET', '/en/manual/02-admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h2#users');
    }

    public function testTheSpanishManualIsTranslatedWithStableAnchorsAndANoticeForTheMissingChapter(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $client->request('GET', '/es/manual/01-getting-started');
        self::assertSelectorTextContains('h1', 'Primeros pasos');
        self::assertSelectorExists('h2#first-steps');
        self::assertSelectorNotExists('[data-testid="manual-translation-notice"]');

        $client->request('GET', '/es/manual/02-admin');
        self::assertSelectorTextContains('h1', 'Administration');
        self::assertSelectorExists('.nowo-manual__notice--missing');
    }
}
