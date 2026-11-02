<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use Nowo\ManualKitBundle\Model\ManualChapter;

/**
 * 1.6: what {@see ManualRenderer} needs to rewrite the links of one chapter — which chapters the viewer may read,
 * the chapters by slug (for their routes in the tree), the manual files by docs-root path and the chapter itself.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class LinkContext
{
    /**
     * @param array<string, bool> $visible chapter slug => readable by this viewer
     * @param array<string, ManualChapter> $chapters chapter slug => chapter
     * @param array<string, string> $files `manual/guide/install.md` => slug (manual chapters, not external files)
     */
    public function __construct(
        public array $visible,
        public array $chapters,
        public array $files,
        public ManualChapter $chapter,
    ) {
    }
}
