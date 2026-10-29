<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_string;
use function sprintf;

/**
 * `nowo_manual_kit` configuration; see docs/CONFIGURATION.md.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const string ALIAS = 'nowo_manual_kit';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(self::ALIAS);
        $tree->getRootNode()
            ->children()
                ->scalarNode('directory')
                    ->info('The manual: manifest.yaml, the source-language chapters, images/ and one folder per translation locale.')
                    ->defaultValue('%kernel.project_dir%/docs/manual')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('source_locale')
                    ->info('Language of the root files.')
                    ->defaultValue('en')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $locale): bool => !self::isLocale($locale))
                        ->then(static fn (mixed $locale): never => throw new InvalidArgumentException(sprintf('Invalid "nowo_manual_kit.source_locale" %s: expected a locale such as "en" or "pt_BR".', json_encode($locale))))
                    ->end()
                ->end()
                ->arrayNode('locales')
                    ->info('Locales that may have a translation folder (default: kernel.enabled_locales).')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $locale): bool => !self::isLocale($locale))
                            ->then(static fn (mixed $locale): never => throw new InvalidArgumentException(sprintf('Invalid entry %s in "nowo_manual_kit.locales": expected locales such as "es" or "pt_BR".', json_encode($locale))))
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('role')
                    ->info('Role (or attribute) needed to read the manual; empty = public.')
                    ->defaultValue('ROLE_USER')
                    ->validate()
                        ->ifTrue(static fn (mixed $role): bool => !is_string($role))
                        ->thenInvalid('"nowo_manual_kit.role" must be a role / security attribute string ("" makes the manual public), got %s.')
                    ->end()
                ->end()
                ->scalarNode('admin_role')
                    ->info('Readers of `audience: admin` chapters and `<!-- audience: admin -->` blocks.')
                    ->defaultValue('ROLE_ADMIN')
                    // '' would mean "everyone" for admin content: never allowed.
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('docs_url')
                    ->info('Base URL of the published docs (e.g. https://github.com/acme/app/blob/main/docs/) for links outside the manual.')
                    ->defaultValue('')
                    ->validate()
                        ->ifTrue(static fn (mixed $url): bool => $url !== '' && !self::isHttpUrl($url))
                        ->thenInvalid('"nowo_manual_kit.docs_url" must be "" or an absolute http(s) URL, got %s.')
                    ->end()
                ->end()
                ->booleanNode('in_app')
                    ->info('manual_href() opens the in-app chapter (true) or always the published copy (false).')
                    ->defaultTrue()
                ->end()
                ->scalarNode('template')
                    ->info('Twig template of a chapter page (variables: rendered, chapters, previous, next).')
                    ->defaultValue('@NowoManualKitBundle/chapter.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('layout')
                    ->info('Layout the default chapter template extends (blocks: title, stylesheets, body).')
                    ->defaultValue('@NowoManualKitBundle/layout.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('standalone_lightbox')
                    ->info('The default chapter template loads the self-contained lightbox script (js/manual-lightbox.js) — for applications that do not register the Stimulus peer in their own build.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('cache')
                    ->info('Cache pool for rendered chapters.')
                    ->defaultValue('cache.app')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('machine_translation')
                    ->info('nowo:manual:translate — machine translation of missing / outdated chapters.')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $machine): bool => $machine['provider'] === 'libretranslate' && $machine['url'] === '')
                        ->thenInvalid('"nowo_manual_kit.machine_translation.url" is required when "provider" is "libretranslate" (e.g. http://libretranslate:5000).')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $machine): bool => $machine['provider'] === 'deepl' && $machine['api_key'] === '')
                        ->thenInvalid('"nowo_manual_kit.machine_translation.api_key" is required when "provider" is "deepl" (e.g. %%env(DEEPL_API_KEY)%%).')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $machine): bool => $machine['max_duration'] < $machine['timeout'])
                        ->thenInvalid('"nowo_manual_kit.machine_translation.max_duration" must be greater than or equal to "timeout".')
                    ->end()
                    ->children()
                        ->enumNode('provider')
                            ->info('none (the command is off), deepl (DeepL API; a key ending in :fx is DeepL API Free) or libretranslate.')
                            ->values(['none', 'deepl', 'libretranslate'])
                            ->defaultValue('none')
                        ->end()
                        ->scalarNode('api_key')
                            ->info('Provider key, e.g. %env(DEEPL_API_KEY)% (optional for a self-hosted LibreTranslate).')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('url')
                            ->info('LibreTranslate base URL (required for it), or a DeepL endpoint override.')
                            ->defaultValue('')
                            ->validate()
                                ->ifTrue(static fn (mixed $url): bool => $url !== '' && !self::isHttpUrl($url))
                                ->thenInvalid('"nowo_manual_kit.machine_translation.url" must be "" or an absolute http(s) URL, got %s.')
                            ->end()
                        ->end()
                        ->floatNode('timeout')
                            ->info('Idle timeout in seconds of each provider request (Symfony HttpClient "timeout").')
                            ->defaultValue(30.0)
                            ->min(1.0)
                        ->end()
                        ->floatNode('max_duration')
                            ->info('Hard deadline in seconds of each provider request, connection included (HttpClient "max_duration").')
                            ->defaultValue(120.0)
                            ->min(1.0)
                        ->end()
                        ->enumNode('formality')
                            ->info('DeepL register: default, less (informal "tú / du") or more.')
                            ->values(['default', 'less', 'more'])
                            ->defaultValue('default')
                        ->end()
                        ->arrayNode('languages')
                            ->info('Locale => provider language code, e.g. {pt: PT-BR} (DeepL defaults: en EN-GB, pt PT-PT).')
                            ->useAttributeAsKey('locale')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('glossary_domains')
                            ->info('Translation domains whose UI labels replace bold labels in the chapters (empty = no glossary).')
                            ->scalarPrototype()->end()
                            ->defaultValue(['messages'])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }

    private static function isLocale(mixed $locale): bool
    {
        return is_string($locale) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale) === 1;
    }

    private static function isHttpUrl(mixed $url): bool
    {
        return is_string($url) && preg_match('#^https?://[^\s/]+#i', $url) === 1;
    }
}
