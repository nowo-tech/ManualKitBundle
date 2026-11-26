<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection\Compiler;

use Nowo\ManualKitBundle\Search\ManualSynonymsInterface;
use Nowo\ManualKitBundle\Search\SearchKitManualSynonyms;
use Nowo\SearchKitBundle\Search\Synonym\SynonymExpander;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.15: wires {@see SearchKitManualSynonyms} as the {@see ManualSynonymsInterface} of the manual search when
 * nowo-tech/search-kit-bundle is registered with `nowo_search_kit.synonyms.enabled` on — so the manual's own search box
 * expands synonyms like the header search does — unless the host already defines the port. Otherwise the port stays
 * undefined and the manual search matches the typed text only.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualSynonymsPass implements CompilerPassInterface
{
    /** SearchKit's synonym switch (bool, since search-kit-bundle 1.8.0). */
    public const string ENABLED_PARAMETER = 'nowo_search_kit.synonyms.enabled';

    /**
     * Registers the SearchKit adapter and aliases the port to it when SearchKit's synonyms are on.
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(ManualSynonymsInterface::class)
            || !$container->hasDefinition(SynonymExpander::class)
            || !$container->hasParameter(self::ENABLED_PARAMETER)
            || $container->getParameter(self::ENABLED_PARAMETER) !== true) {
            return;
        }
        $container->register(SearchKitManualSynonyms::class, SearchKitManualSynonyms::class)
            ->setArgument('$expander', new Reference(SynonymExpander::class));
        $container->setAlias(ManualSynonymsInterface::class, SearchKitManualSynonyms::class);
    }
}
