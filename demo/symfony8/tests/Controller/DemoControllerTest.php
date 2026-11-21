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
        self::assertSelectorTextContains('header.nowo-ui-header', 'demo');
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

    /** 1.12: device_views is on in the demo: 03-guide/projects has desktop / phone blocks and a phone companion. */
    public function testDeviceViewsShowTheSwitchOnChaptersWithDeviceContent(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('demo', 'demo', ['ROLE_USER']));

        $crawler = $client->request('GET', '/en/manual/03-guide/projects?device=phone');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('article[data-nowo-manual-devices="768"][data-nowo-manual-device-initial="phone"]');
        self::assertSelectorExists('[data-testid="manual-device-switch"] button[data-nowo-manual-device-option="phone"]');
        self::assertSelectorExists('.manual-prose .manual-device--desktop');
        self::assertSelectorExists('.manual-prose .manual-device--phone');
        self::assertSelectorExists('figure[data-nowo-manual-device="phone"] img[src="/en/manual/images/dashboard.phone.svg"]');
        self::assertCount(1, $crawler->filter('script[src$="js/manual-devices.js"]'));

        $client->request('GET', '/en/manual/images/dashboard.phone.svg');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/en/manual/03-guide');
        self::assertSelectorNotExists('[data-testid="manual-device-switch"]');
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

    public function testOldChapterLinksRedirectToTheirNewPlace(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('demo', 'demo', ['ROLE_USER']));

        $client->request('GET', '/es/manual/01-start');
        self::assertResponseRedirects('/es/manual/01-getting-started', 301);
        $client->request('GET', '/en/manual/04-new-project');
        self::assertResponseRedirects('/en/manual/03-guide/projects#create', 301);
        $client->request('GET', '/en/manual/00-introduction');
        self::assertResponseRedirects('/en/manual/03-guide?moved_from=00-introduction', 301);

        $client->request('GET', '/en/manual/03-guide?moved_from=00-introduction');
        self::assertSelectorExists('article[data-nowo-manual-anchors]');
        self::assertSelectorExists('script[src="/bundles/nowomanualkit/js/manual-anchors.js"]');

        $client->request('GET', '/en/manual/old-admin');
        self::assertResponseStatusCodeSame(404);
    }

    /** 1.13: a named audience — the API chapter and its block are for developers only. */
    public function testDevelopersReadTheApiChapterAndMembersDoNot(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('dev', 'dev', ['ROLE_USER', 'ROLE_DEVELOPER']));
        $crawler = $client->request('GET', '/en/manual/04-api');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h2#rotating-tokens');
        self::assertCount(1, $crawler->filter('[data-testid="manual-nav-04-api"]'));

        $client->loginUser(new InMemoryUser('demo', 'demo', ['ROLE_USER']));
        $crawler = $client->request('GET', '/en/manual');
        self::assertCount(0, $crawler->filter('[data-testid="manual-nav-04-api"]'));
        $client->request('GET', '/en/manual/04-api');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/en/manual/portal-home');
        self::assertResponseStatusCodeSame(404, 'a portal chapter is never served in the staff manual');
    }

    /** 1.13: the customer portal root serves only `customer` chapters, in its own firewall. */
    public function testThePortalServesOnlyCustomerChapters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portal/help');
        self::assertResponseStatusCodeSame(401);

        $client->loginUser(new InMemoryUser('contact', 'contact', ['ROLE_CRM_CONTACT']), 'portal');
        $crawler = $client->request('GET', '/portal/help');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Customer help');
        self::assertSelectorNotExists('h2#staff-notes');
        self::assertCount(0, $crawler->filter('.manual-prose a[href*="01-getting-started"]'));
        $client->request('GET', '/portal/help/01-getting-started');
        self::assertResponseStatusCodeSame(404);
    }
}
