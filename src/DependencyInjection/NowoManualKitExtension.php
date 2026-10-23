<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection;

use Nowo\ManualKitBundle\Render\ManualRenderer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
        /** @var array{directory: string, source_locale: string, locales: list<string>, role: string, admin_role: string, docs_url: string, in_app: bool, template: string, layout: string, cache: string} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $value) {
            $container->setParameter(Configuration::ALIAS . '.' . $key, $value);
        }
        if ($config['locales'] === []) {
            $container->setParameter(Configuration::ALIAS . '.locales', '%kernel.enabled_locales%');
        }

        new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'))->load('services.php');
        $container->getDefinition(ManualRenderer::class)->setArgument('$cache', new Reference($config['cache']));
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
