<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReader;
use Nowo\ManualKitBundle\Audience\ManualReaderResolver;
use Nowo\ManualKitBundle\Audience\ManualReaderResolverInterface;
use Nowo\ManualKitBundle\Audience\ManualRoot;
use Nowo\ManualKitBundle\Audience\ManualRootResolver;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\DeviceViews;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Render\ReaderRendering;
use Nowo\ManualKitBundle\Routing\ManualRoutes;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ReadableChapters;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function trim;

/**
 * The manual search (1.15; the matching of {@see ManualSearchProvider} since 1.6): the chapters and their h2–h4
 * sections whose title or tags match ({@see ManualSearchText}) one of the query texts, among those the reader of the
 * request may open, in the request locale. Chapter titles first, then sections in manual order. Nested chapters are
 * titled with their parents ("Parent › Child"); a hit in a device view links to `?device=<view>#<section>`.
 *
 * Usable without SearchKit, for the manual's own search box: search() adds the alternatives of the optional
 * {@see ManualSynonymsInterface}. SearchKit's header group passes the alternatives the engine already built (find()).
 * Synonyms only widen what matches: the root of the request, the reader's audiences, the host's per-chapter rule and
 * the blocks the reader may see decide what is listed, exactly as without them.
 *
 * Stateless (FrankenPHP worker mode): the reader, the root and the locale are resolved from the request on every call.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualSearch
{
    /** Most query texts matched per search (the text and its alternatives). */
    public const int MAX_TEXTS = 25;

    /**
     * Receives the services used to search the manual chapters the current reader may open.
     */
    public function __construct(
        private ManualCatalogInterface $catalog,
        private ManualRendererInterface $renderer,
        private ManualAccessInterface $access,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private RequestStack $requests,
        private ManualAudiences $audiences = new ManualAudiences(),
        private ?ManualReaderResolverInterface $readers = null,
        private ManualRootResolver $roots = new ManualRootResolver(),
        private ?ManualSynonymsInterface $synonyms = null,
    ) {
    }

    /**
     * The manual's own search: the text and, when a {@see ManualSynonymsInterface} is wired, its alternatives in the
     * request locale.
     *
     * @param string $text The text as typed
     * @param int $limit Most hits returned
     *
     * @return ManualSearchResult|null Null when nothing matched or the request has no reader
     */
    public function search(string $text, int $limit): ?ManualSearchResult
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $alternatives = $this->synonyms?->alternatives($text, $this->requests->getCurrentRequest()?->getLocale()) ?? [];

        return $this->find([$text, ...$alternatives], $limit);
    }

    /**
     * The chapters and sections matching any of the texts.
     *
     * @param list<string> $texts The text first, then its alternatives (at most MAX_TEXTS are used)
     * @param int $limit Most hits returned
     *
     * @return ManualSearchResult|null Null when nothing matched or the request has no reader
     */
    public function find(array $texts, int $limit): ?ManualSearchResult
    {
        $words   = ManualSearchText::words(array_slice($texts, 0, self::MAX_TEXTS));
        $request = $this->requests->getCurrentRequest();
        $root    = $this->roots->resolve($request);
        $reader  = $root instanceof ManualRoot ? ($this->readers ?? new ManualReaderResolver($this->access, $this->audiences))->reader($root) : null;
        if ($words === [] || !$root instanceof ManualRoot || !$reader instanceof ManualReader) {
            return null;
        }
        $locale = $request?->getLocale();

        $chapters = [];
        $sections = [];
        // 1.13: the chapters of the root the reader may see by audience; 1.9: then the host's per-chapter rule.
        $visible = array_values(array_filter($this->catalog->all($locale), fn (ManualChapter $chapter): bool => $this->audiences->canSee($chapter, $reader)));
        foreach (ReadableChapters::filter($visible, $visible, $this->access) as $chapter) {
            $url = ManualRoutes::generate($this->urls, $chapter, [], $root);
            // 1.6: a nested chapter reads "Parent › Child" (its own title and tags are what match).
            $title = $chapter->trailTitle();
            if (ManualSearchText::matchesWords($words, $chapter->title, $chapter->tags)) {
                $chapters[] = new ManualSearchHit($title, $url, $this->groupLabel($chapter), 'tabler:book', false);
            }
            $rendered = ReaderRendering::render($this->renderer, $chapter, $reader);
            foreach ($rendered->toc as $entry) {
                if (ManualSearchText::matchesWords($words, $entry['text'], $entry['tags'])) {
                    // 1.12: a section of one view (desktop / phone) opens that view.
                    $target     = isset($entry['device']) && DeviceViews::isDevice($entry['device']) ? ManualRoutes::generate($this->urls, $chapter, [DeviceViews::QUERY => $entry['device']], $root) : $url;
                    $sections[] = new ManualSearchHit($entry['text'], $target . '#' . $entry['id'], $title, 'tabler:section', true);
                }
            }
        }
        $hits = array_slice([...$chapters, ...$sections], 0, $limit);

        return $hits === [] ? null : new ManualSearchResult($hits, count($chapters) + count($sections));
    }

    private function groupLabel(ManualChapter $chapter): string
    {
        return $this->translator->trans('title', [], 'NowoManualKitBundle') . ' · ' . $this->translator->trans('group.' . $chapter->group, [], 'NowoManualKitBundle');
    }
}
