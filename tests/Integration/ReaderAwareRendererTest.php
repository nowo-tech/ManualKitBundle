<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use LogicException;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReader;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Render\ReaderRendering;
use Nowo\ManualKitBundle\Tests\Support\HostManualRenderer;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use PHPUnit\Framework\TestCase;

/**
 * 1.13, audit MK113-M1: a renderer without renderFor() never renders another root — the build fails with roots
 * configured, and the runtime guard refuses it — and the stray-marker rules (MK113-H1, MK113-L3).
 */
final class ReaderAwareRendererTest extends TestCase
{
    private const array ROOTS = [
        'audiences' => ['customer' => 'ROLE_CRM_CONTACT'],
        'roots'     => ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'firewalls' => ['portal']]],
    ];

    public function testRootsWithA112RendererStopTheBuild(): void
    {
        $kernel = new TestKernel(self::ROOTS, false, null, null, null, HostManualRenderer::class);

        try {
            $kernel->boot();
            self::fail('The container should not build.');
        } catch (LogicException $e) {
            self::assertStringContainsString('"nowo_manual_kit.roots" is configured, so the manual renderer (' . HostManualRenderer::class, $e->getMessage());
            self::assertStringContainsString('must implement Nowo\ManualKitBundle\Render\ReaderAwareManualRendererInterface', $e->getMessage());
        } finally {
            $kernel->shutdown();
        }
    }

    public function testRootsWithTheBundleRendererBuild(): void
    {
        $kernel = new TestKernel(self::ROOTS);
        $kernel->boot();
        self::assertInstanceOf(ManualRenderer::class, $kernel->getContainer()->get(ManualRenderer::class));
        $kernel->shutdown();
    }

    public function testA112RendererOnlyServesTheDefaultRoot(): void
    {
        $chapter = new ManualChapter('a', 'using', false, 'tabler:book', 'A', '/tmp/a.md');
        self::assertSame('<p>host-rendered a</p>', ReaderRendering::render(new HostManualRenderer(), $chapter, ManualReader::legacy(true))->html);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('it cannot render the chapters of the root "portal"');
        ReaderRendering::render(new HostManualRenderer(), $chapter, new ManualReader('portal', ['customer']));
    }

    public function testOnlyAReaderOfEveryConfiguredAudienceKeepsTheTextAfterAStrayMarker(): void
    {
        $audiences = new ManualAudiences(['customer' => 'ROLE_C', 'user' => 'ROLE_U'], 'user', ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_C', 'home' => 'README', 'firewalls' => ['portal']]]);
        self::assertFalse($audiences->isWhole(new ManualReader('portal', ['customer'])), 'a one-audience root is never whole');
        self::assertFalse($audiences->isWhole(new ManualReader('default', ['admin', 'member', 'user'])));
        self::assertTrue(new ManualAudiences()->isWhole(ManualReader::legacy(true)), 'the 1.12 administrator');

        $nested = "a\n<!-- audience: customer -->\nc\n<!-- audience: user -->\nstaff\n<!-- /audience -->\ntail\n<!-- /audience -->\n";
        self::assertSame("a\n\nc\n", ManualRenderer::audiencesFiltered($nested, ['customer']));

        // MK113-L3: the text after a stray github opening never reaches the app, not even a whole reader.
        $github = "a\n<!-- audience: github -->\ncredentials\n";
        self::assertSame("a\n", ManualRenderer::audienceFiltered($github, true));
        self::assertSame("a\n\nsecret", ManualRenderer::audienceFiltered("a\n<!-- audience: admin -->\nsecret", true), 'a stray admin marker as in 1.12');
    }

    public function testTheConfigurationIsPartOfTheCacheKey(): void
    {
        self::assertSame(new ManualAudiences()->fingerprint(), new ManualAudiences()->fingerprint());
        self::assertNotSame(new ManualAudiences()->fingerprint(), new ManualAudiences(defaultAudience: 'admin')->fingerprint());
        self::assertNotSame(new ManualAudiences()->fingerprint(), new ManualAudiences(defaultFirewalls: ['main'])->fingerprint());
    }
}
