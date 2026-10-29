<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use Nowo\ManualKitBundle\DependencyInjection\NowoManualKitExtension;
use Nowo\ManualKitBundle\NowoManualKitBundle;
use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use Nowo\ManualKitBundle\Translation\DeepLTranslator;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BundleBootTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame([
            'directory'           => '%kernel.project_dir%/docs/manual',
            'source_locale'       => 'en',
            'locales'             => [],
            'role'                => 'ROLE_USER',
            'admin_role'          => 'ROLE_ADMIN',
            'docs_url'            => '',
            'in_app'              => true,
            'template'            => '@NowoManualKitBundle/chapter.html.twig',
            'layout'              => '@NowoManualKitBundle/layout.html.twig',
            'standalone_lightbox' => false,
            'cache'               => 'cache.app',
            'machine_translation' => ['provider' => 'none', 'api_key' => '', 'url' => '', 'timeout' => 30.0, 'max_duration' => 120.0, 'formality' => 'default', 'languages' => [], 'glossary_domains' => ['messages']],
        ], new Processor()->processConfiguration(new Configuration(), []));
    }

    public function testTheBundleBootsWithTheEnabledLocalesAsTranslationLocales(): void
    {
        $bundle = new NowoManualKitBundle();
        self::assertInstanceOf(NowoManualKitExtension::class, $bundle->getContainerExtension());
        self::assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
        self::assertSame('nowo_manual_kit', new NowoManualKitExtension()->getAlias());

        $kernel = new TestKernel();
        $kernel->boot();
        $catalog = $kernel->getContainer()->get(ManualCatalog::class);
        self::assertInstanceOf(ManualCatalog::class, $catalog);
        self::assertSame(['en', 'es'], $catalog->locales());
        self::assertSame(['en', 'es'], $kernel->getContainer()->getParameter('nowo_manual_kit.locales'));
        $kernel->shutdown();

        $kernel = new TestKernel(['locales' => ['es', 'fr']]);
        $kernel->boot();
        self::assertSame(['es', 'fr'], $kernel->getContainer()->getParameter('nowo_manual_kit.locales'));
        $kernel->shutdown();
    }

    public function testMachineTranslationIsOffUntilAProviderIsSet(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();
        $markdown = $kernel->getContainer()->get(MarkdownTranslator::class);
        self::assertInstanceOf(MarkdownTranslator::class, $markdown);
        self::assertFalse($markdown->enabled());
        $kernel->shutdown();

        $kernel = new TestKernel(['machine_translation' => ['provider' => 'deepl', 'api_key' => 'k:fx', 'formality' => 'less']]);
        $kernel->boot();
        $markdown = $kernel->getContainer()->get(MarkdownTranslator::class);
        self::assertInstanceOf(MarkdownTranslator::class, $markdown);
        self::assertTrue($markdown->enabled());
        $deepl = $kernel->getContainer()->get('nowo_manual_kit.machine_translator');
        self::assertInstanceOf(DeepLTranslator::class, $deepl);
        self::assertSame(30.0, new ReflectionProperty($deepl, 'timeout')->getValue($deepl));
        self::assertSame(120.0, new ReflectionProperty($deepl, 'maxDuration')->getValue($deepl));
        $kernel->shutdown();
    }

    /** REQ-ASSETS-004: the bundle registers its own asset package for public/bundles/nowomanualkit. */
    public function testPrependRegistersTheAssetPackage(): void
    {
        $container = new ContainerBuilder();
        new NowoManualKitExtension()->prepend($container);
        self::assertSame([], $container->getExtensionConfig('framework'), 'no FrameworkBundle, nothing to prepend');

        $container->registerExtension(new FrameworkExtension());
        new NowoManualKitExtension()->prepend($container);
        self::assertSame([['assets' => ['packages' => ['nowo_manual_kit' => ['base_path' => '/bundles/nowomanualkit']]]]], $container->getExtensionConfig('framework'));
    }

    /** REQ-SF-006: an invalid configuration stops the container compile with the path and the allowed values. */
    public function testAnInvalidConfigurationFailsTheContainerCompile(): void
    {
        $kernel = new TestKernel(['machine_translation' => ['provider' => 'libretranslate']]);

        try {
            $kernel->boot();
            self::fail('The container should not compile.');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('"nowo_manual_kit.machine_translation"', $e->getMessage());
            self::assertStringContainsString('"nowo_manual_kit.machine_translation.url" is required when "provider" is "libretranslate"', $e->getMessage());
        }
    }
}
