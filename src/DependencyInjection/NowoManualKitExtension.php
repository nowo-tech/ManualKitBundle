<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection;

use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Translation\DeepLTranslator;
use Nowo\ManualKitBundle\Translation\LibreTranslateTranslator;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use Nowo\ManualKitBundle\Translation\UiGlossary;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Loads `nowo_manual_kit` into `nowo_manual_kit.*` container parameters and `Resources/config/services.php`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoManualKitExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{directory: string, source_locale: string, locales: list<string>, role: string, admin_role: string, docs_url: string, in_app: bool, template: string, layout: string, cache: string, machine_translation: array{provider: string, api_key: string, url: string, formality: string, languages: array<string, string>, glossary_domains: list<string>}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $value) {
            $container->setParameter(Configuration::ALIAS . '.' . $key, $value);
        }
        if ($config['locales'] === []) {
            $container->setParameter(Configuration::ALIAS . '.locales', '%kernel.enabled_locales%');
        }

        new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'))->load('services.php');
        $container->getDefinition(ManualRenderer::class)->setArgument('$cache', new Reference($config['cache']));

        $machine = $config['machine_translation'];
        $container->getDefinition(UiGlossary::class)
            ->setArgument('$translator', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$domains', $machine['glossary_domains']);
        $translator = match ($machine['provider']) {
            'deepl'          => new Definition(DeepLTranslator::class, [new Reference('http_client'), $machine['api_key'], $machine['url'], $machine['formality'], $machine['languages']]),
            'libretranslate' => new Definition(LibreTranslateTranslator::class, [new Reference('http_client'), $machine['url'], $machine['api_key'], $machine['languages']]),
            default          => null,
        };
        if ($translator instanceof Definition) {
            $container->setDefinition('nowo_manual_kit.machine_translator', $translator);
            $container->getDefinition(MarkdownTranslator::class)->setArgument('$translator', new Reference('nowo_manual_kit.machine_translator'));
        }
    }

    /**
     * Returns the `nowo_manual_kit` configuration alias.
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
