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
     * @param list<array{level: int, id: string, text: string, tags: list<string>, device?: string}> $toc h2–h4 (tags: the
     *                                                                                                    section's `<!-- tags: … -->`;
     *                                                                                                    1.12 device: set
     *                                                                                                    inside a device block)
     * @param bool $deviceViews 1.12: the chapter has desktop / phone content (device blocks or phone companions) —
     *                          the page then shows the view switch
     */
    public function __construct(
        public ManualChapter $chapter,
        public string $html,
        public array $toc,
        public bool $deviceViews = false,
    ) {
    }
}
