<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReaderResolverInterface;
use Nowo\ManualKitBundle\Audience\ManualRootResolver;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\SearchKitBundle\Contract\SearchProviderInterface;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchTerm;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_map;

/**
 * SearchKit group "Manual" (registered only when nowo-tech/search-kit-bundle is installed): the chapters and their
 * h2–h4 sections whose title or tags match ({@see ManualSearchText}), among those the viewer may read, in the request
 * locale. Chapter titles first, then sections in manual order. 1.6: nested chapters are indexed with their own tags,
 * behind the same access rules, and titled with their parents ("Parent › Child"). 1.12: both desktop and phone
 * sections are indexed; a hit in one of them links to `?device=<view>#<section>` so the page opens that view.
 *
 * 1.13: there is no shared index — every query is answered for the reader of the request: the manual root of the
 * request (its firewall's, {@see ManualRootResolver}) and the audiences of it the user holds. Chapters of other roots
 * or audiences, and the sections of blocks the reader may not see, never match, so neither their titles nor their
 * URLs leave the server.
 *
 * 1.15: the query matches through its synonym alternatives too — `SearchQuery::terms()`, which SearchKit's engine fills
 * when its synonyms are on (the typed text alone otherwise) — with the same access rules. The matching itself is
 * {@see ManualSearch}, which the manual's own search box can use without SearchKit.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualSearchProvider implements SearchProviderInterface
{
    private ManualSearch $search;

    /**
     * Receives the services used to search the manual chapters the current user can read.
     */
    public function __construct(
        ManualCatalogInterface $catalog,
        ManualRendererInterface $renderer,
        ManualAccessInterface $access,
        UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        RequestStack $requests,
        ManualAudiences $audiences = new ManualAudiences(),
        ?ManualReaderResolverInterface $readers = null,
        ManualRootResolver $roots = new ManualRootResolver(),
    ) {
        $this->search = new ManualSearch($catalog, $renderer, $access, $urls, $translator, $requests, $audiences, $readers, $roots);
    }

    /**
     * {@inheritDoc}
     */
    public function search(SearchQuery $query): ?SearchGroup
    {
        $texts  = array_map(static fn (SearchTerm $term): string => $term->text, $query->terms());
        $result = $this->search->find($texts, $query->limit);
        if (!$result instanceof ManualSearchResult) {
            return null;
        }
        $items = array_map(static fn (ManualSearchHit $hit): SearchItem => new SearchItem($hit->title, $hit->url, $hit->subtitle, $hit->icon), $result->hits);

        return new SearchGroup('manual', $this->translator->trans('search.group', [], 'NowoManualKitBundle'), $items, $result->total);
    }
}
