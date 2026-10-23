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
use Symfony\Component\Config\Definition\Processor;

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
            'cache'               => 'cache.app',
            'machine_translation' => ['provider' => 'none', 'api_key' => '', 'url' => '', 'formality' => 'default', 'languages' => [], 'glossary_domains' => ['messages']],
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
        self::assertInstanceOf(DeepLTranslator::class, $kernel->getContainer()->get('nowo_manual_kit.machine_translator'));
        $kernel->shutdown();
    }
}
