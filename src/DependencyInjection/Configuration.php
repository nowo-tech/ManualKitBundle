<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * `nowo_manual_kit` configuration; see docs/CONFIGURATION.md.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'nowo_manual_kit';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::ALIAS);
        $tree->getRootNode()
            ->children()
                ->scalarNode('directory')
                    ->info('The manual: manifest.yaml, the source-language chapters, images/ and one folder per translation locale.')
                    ->defaultValue('%kernel.project_dir%/docs/manual')
                ->end()
                ->scalarNode('source_locale')
                    ->info('Language of the root files.')
                    ->defaultValue('en')
                ->end()
                ->arrayNode('locales')
                    ->info('Locales that may have a translation folder (default: kernel.enabled_locales).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('role')
                    ->info('Role (or attribute) needed to read the manual; empty = public.')
                    ->defaultValue('ROLE_USER')
                ->end()
                ->scalarNode('admin_role')
                    ->info('Readers of `audience: admin` chapters and `<!-- audience: admin -->` blocks.')
                    ->defaultValue('ROLE_ADMIN')
                ->end()
                ->scalarNode('docs_url')
                    ->info('Base URL of the published docs (e.g. https://github.com/acme/app/blob/main/docs/) for links outside the manual.')
                    ->defaultValue('')
                ->end()
                ->booleanNode('in_app')
                    ->info('manual_href() opens the in-app chapter (true) or always the published copy (false).')
                    ->defaultTrue()
                ->end()
                ->scalarNode('template')
                    ->info('Twig template of a chapter page (variables: rendered, chapters, previous, next).')
                    ->defaultValue('@NowoManualKitBundle/chapter.html.twig')
                ->end()
                ->scalarNode('layout')
                    ->info('Layout the default chapter template extends (blocks: title, stylesheets, body).')
                    ->defaultValue('@NowoManualKitBundle/layout.html.twig')
                ->end()
                ->scalarNode('cache')
                    ->info('Cache pool for rendered chapters.')
                    ->defaultValue('cache.app')
                ->end()
            ->end();

        return $tree;
    }
}
