<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Functional;

use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * 1.8: `groups` (order and labels of the navigation) and the chapter page blocks (manual_nav, manual_nav_start,
 * manual_body, manual_body_start, manual_aside).
 */
final class NavGroupsAndBlocksTest extends WebTestCase
{
    /**
     * @param array{manual?: array<string, mixed>} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(($options['manual'] ?? []) + ['directory' => '%kernel.project_dir%/tests/Fixtures/tree/manual']);
    }

    public function testConfiguredGroupsComeFirstWithTheirLabels(): void
    {
        $client = self::createClient(['manual' => ['groups' => [
            ['key' => 'administration', 'label' => 'host.group.ops'],
            'missing',
        ]]], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'x']);
        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertResponseIsSuccessful();

        self::assertSame(['host.group.ops', 'Using the application'], $crawler->filter('[data-testid="manual-nav"] .nowo-manual__group')->each(static fn ($p): string => $p->text()));
        self::assertCount(1, $crawler->filter('[data-testid="manual-branch-guide"]'), 'the nested tree is still drawn');
    }

    public function testGroupsWithoutReadableChaptersAreLeftOut(): void
    {
        $client  = self::createClient(['manual' => ['groups' => ['administration', 'using']]], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $crawler = $client->request('GET', '/en/manual');
        self::assertResponseIsSuccessful();

        self::assertSame(['Using the application'], $crawler->filter('[data-testid="manual-nav"] .nowo-manual__group')->each(static fn ($p): string => $p->text()), 'administration has no chapter a member may read');
    }

    public function testAHostTemplateFillsTheBlocks(): void
    {
        $client  = self::createClient(['manual' => ['template' => '@ManualTest/host_chapter.html.twig', 'groups' => [['key' => 'using', 'label' => 'host.using']]]], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'x']);
        $crawler = $client->request('GET', '/en/manual/guide/install');
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('[data-testid="manual-nav"]'), 'manual_nav replaced');
        self::assertSame('host.using', $crawler->filter('[data-testid="host-group-using"]')->text());
        self::assertCount(1, $crawler->filter('[data-testid="host-nav"] [data-testid="manual-branch-guide"][open]'), 'the bundle macro draws the open branch');
        self::assertCount(1, $crawler->filter('[data-testid="manual-chapter"] [data-testid="host-search"]'), 'manual_body_start inside the article');
        self::assertCount(1, $crawler->filter('[data-testid="manual-chapter"] [data-testid="manual-breadcrumbs"]'), 'the rest of manual_body is the bundle\'s');
        self::assertCount(1, $crawler->filter('[data-testid="manual"] > [data-testid="host-aside"]'));
    }

    public function testNavStartAndBodyBlocks(): void
    {
        $client  = self::createClient(['manual' => ['template' => '@ManualTest/host_nav_start.html.twig']], ['PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'x']);
        $crawler = $client->request('GET', '/en/manual/guide');
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('[data-testid="manual-nav"] > [data-testid="host-nav-search"]'));
        self::assertCount(1, $crawler->filter('[data-testid="host-body"]'));
        self::assertCount(0, $crawler->filter('[data-testid="manual-chapter"]'));
    }

    public function testGroupsConfiguration(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [['groups' => ['using', ['key' => 'ops', 'label' => 'ops.title', 'translation_domain' => 'admin']]]]);
        self::assertSame([
            ['key' => 'using', 'label' => null, 'translation_domain' => 'messages'],
            ['key' => 'ops', 'label' => 'ops.title', 'translation_domain' => 'admin'],
        ], $config['groups']);

        $this->expectException(InvalidConfigurationException::class);
        new Processor()->processConfiguration(new Configuration(), [['groups' => ['using', ['key' => 'using']]]]);
    }
}
