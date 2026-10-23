<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Model;

/**
 * A chapter rendered for one audience and locale: sanitised HTML and its "On this page" entries (h2–h4).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RenderedManualChapter
{
    /**
     * @param list<array{level: int, id: string, text: string, tags: list<string>}> $toc h2–h4 (tags: the section's `<!-- tags: … -->`)
     */
    public function __construct(
        public ManualChapter $chapter,
        public string $html,
        public array $toc,
    ) {
    }
}
