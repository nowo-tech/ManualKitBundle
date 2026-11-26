<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use Nowo\SearchKitBundle\Model\SearchTerm;
use Nowo\SearchKitBundle\Search\Synonym\SynonymExpander;

use function array_map;

/**
 * {@see ManualSynonymsInterface} over SearchKit's synonyms (1.15): the alternatives SearchKit's `SynonymExpander` builds
 * from every `SearchSynonymProviderInterface` (the built-in concept groups, the host's dictionary), with the same
 * locales and bounds as the header search. Registered only when nowo-tech/search-kit-bundle is installed with
 * `nowo_search_kit.synonyms.enabled` on and no host service is aliased to the port. Stateless.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class SearchKitManualSynonyms implements ManualSynonymsInterface
{
    /**
     * @param SynonymExpander $expander SearchKit's expander
     */
    public function __construct(
        private SynonymExpander $expander,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function alternatives(string $text, ?string $locale): array
    {
        return array_map(static fn (SearchTerm $term): string => $term->text, $this->expander->expand($text, $locale));
    }
}
