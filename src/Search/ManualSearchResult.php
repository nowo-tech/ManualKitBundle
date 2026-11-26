<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

/**
 * What a manual search found (1.15): the first hits (chapters, then sections, in manual order) and how many matched.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualSearchResult
{
    /**
     * @param list<ManualSearchHit> $hits At most the limit asked for
     * @param int $total Every matching chapter and section the reader may open
     */
    public function __construct(
        public array $hits,
        public int $total,
    ) {
    }
}
