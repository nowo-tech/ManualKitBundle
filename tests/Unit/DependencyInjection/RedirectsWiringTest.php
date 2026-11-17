<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use Nowo\ManualKitBundle\DependencyInjection\NowoManualKitExtension;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectMap;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectsInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_map;
use function dirname;

/**
 * 1.11: the redirect map is compiled when the container is built — config and manifest file merged, an invalid map
 * (absolute URL, cycle) refused, the files read tracked.
 */
final class RedirectsWiringTest extends TestCase
{
    private const string FIXTURE = '/tests/Fixtures/redirects/manual';

    public function testTheConfigurationKeepsDashesAndAnchorsInKeys(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [
            ['redirects' => ['old-slug' => 'new-slug', 'old-slug#old_anchor' => 'new-slug#anchor']],
            ['redirects' => ['other' => 'faq']],
        ]);

        self::assertSame(['old-slug' => 'new-slug', 'old-slug#old_anchor' => 'new-slug#anchor', 'other' => 'faq'], $config['redirects']);
        self::assertSame([], new Processor()->processConfiguration(new Configuration(), [[]])['redirects']);
    }

    public function testTheMapIsCompiledFromTheConfigurationAndTheManifestFile(): void
    {
        $container = $this->load(['directory' => '%kernel.project_dir%' . self::FIXTURE, 'redirects' => ['07-inline' => 'faq']]);

        $rules = $container->getParameter('nowo_manual_kit.redirects');
        self::assertIsArray($rules);
        self::assertSame('faq', $rules['07-inline']);
        self::assertSame('01-install', $rules['05-chain'], 'the rules as written');
        self::assertSame(ChapterRedirectMap::class, (string) $container->getAlias(ChapterRedirectsInterface::class));

        $arguments = $container->getDefinition(ChapterRedirectMap::class)->getArguments();
        self::assertSame($rules, $arguments[0]);
        self::assertSame('setup', $arguments[1]['05-chain'], 'chains collapsed');
        self::assertSame(['docker' => 'deploy#docker', 'intro' => 'setup#overview'], $arguments[2]['setup']['04-split']);

        $tracked = array_map(static fn (object $resource): string => (string) $resource, $container->getResources());
        $root    = dirname(__DIR__, 3);
        self::assertContains($root . self::FIXTURE . '/redirects.yaml', $tracked);
        self::assertContains($root . self::FIXTURE . '/manifest.yaml', $tracked);
    }

    public function testAnAbsoluteUrlStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid manual redirect map (nowo_manual_kit.redirects and the manifest redirects): Redirect "old" → "https://evil.example": targets are internal chapter slugs');

        $this->load(['directory' => '/nowhere', 'redirects' => ['old' => 'https://evil.example']]);
    }

    public function testACycleStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Redirect cycle: 01-install → setup → 01-install.');

        // The manifest file maps 01-install → setup; the inline rule closes the loop.
        $this->load(['directory' => '%kernel.project_dir%' . self::FIXTURE, 'redirects' => ['setup' => '01-install']]);
    }

    public function testAnEnvDirectoryUsesTheInlineMapOnly(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.enabled_locales', ['en']);
        $container->setParameter('env(MANUAL_DIR)', dirname(__DIR__, 3) . self::FIXTURE);
        new NowoManualKitExtension()->load([['directory' => '%env(MANUAL_DIR)%', 'redirects' => ['a' => 'b']]], $container);

        self::assertSame(['a' => 'b'], $container->getParameter('nowo_manual_kit.redirects'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.enabled_locales', ['en']);
        new NowoManualKitExtension()->load([$config], $container);

        return $container;
    }
}
