<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

/**
 * One row of a manual search (1.15): a chapter or one of its sections the reader may open.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualSearchHit
{
    /**
     * @param string $title Chapter title ("Parent › Child" when nested) or section heading
     * @param string $url Chapter URL, with `#<section>` (and `?device=` for a device view) for a section
     * @param string $subtitle "Manual · <group>" for a chapter, the chapter title for a section
     * @param string $icon UX Icons name (`tabler:book`, `tabler:section`)
     * @param bool $section Whether the hit is a section
     */
    public function __construct(
        public string $title,
        public string $url,
        public string $subtitle,
        public string $icon,
        public bool $section,
    ) {
    }
}
