<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Nowo\SearchKitBundle\Contract\SearchProviderInterface;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_slice;
use function count;

/**
 * SearchKit group "Manual" (registered only when nowo-tech/search-kit-bundle is installed): the chapters and their
 * h2–h4 sections whose title or tags match ({@see ManualSearchText}), among those the viewer may read, in the request
 * locale. Chapter titles first, then sections in manual order.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualSearchProvider implements SearchProviderInterface
{
    /**
     * Receives the services used to search the manual chapters the current user can read.
     */
    public function __construct(
        private ManualCatalog $catalog,
        private ManualRenderer $renderer,
        private ManualAccess $access,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private RequestStack $requests,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function search(SearchQuery $query): ?SearchGroup
    {
        if (!$this->access->canRead()) {
            return null;
        }
        $admin  = $this->access->isAdmin();
        $locale = $this->requests->getCurrentRequest()?->getLocale();

        $chapters = [];
        $sections = [];
        foreach ($this->catalog->visible($admin, $locale) as $chapter) {
            $url = $chapter->isHome() ? $this->urls->generate('nowo_manual_index') : $this->urls->generate('nowo_manual_chapter', ['chapter' => $chapter->slug]);
            if (ManualSearchText::matches($query->text, $chapter->title, $chapter->tags)) {
                $chapters[] = new SearchItem($chapter->title, $url, $this->groupLabel($chapter), 'tabler:book');
            }
            foreach ($this->renderer->render($chapter, $admin)->toc as $entry) {
                if (ManualSearchText::matches($query->text, $entry['text'], $entry['tags'])) {
                    $sections[] = new SearchItem($entry['text'], $url . '#' . $entry['id'], $chapter->title, 'tabler:section');
                }
            }
        }
        $items = array_slice([...$chapters, ...$sections], 0, $query->limit);

        return $items === [] ? null : new SearchGroup('manual', $this->translator->trans('search.group', [], 'NowoManualKitBundle'), $items, count($chapters) + count($sections));
    }

    private function groupLabel(ManualChapter $chapter): string
    {
        return $this->translator->trans('title', [], 'NowoManualKitBundle') . ' · ' . $this->translator->trans('group.' . $chapter->group, [], 'NowoManualKitBundle');
    }
}
