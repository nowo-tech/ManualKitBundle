<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Model;

/**
 * One chapter of the manual (`{directory}/{slug}.md`, listed in `manifest.yaml`), resolved for one locale.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualChapter
{
    /** The manual's home page (the `README.md` of the directory). */
    public const string HOME = 'README';

    /**
     * @param list<string> $tags search keywords and synonyms (manifest `tags`), in any locale
     * @param bool $external 1.2: a Markdown file outside the manual folder (manifest `file`), never translated
     * @param string $docsDir 1.2: the folder of the file, relative to the docs root (`manual`, or `` for
     *                        docs/CHANGELOG.md): where its relative links to other docs start
     * @param int $tocDepth 1.2: deepest heading level listed in "On this page" (manifest `toc_depth`)
     */
    public function __construct(
        public string $slug,
        public string $group,
        public bool $adminOnly,
        public string $icon,
        public string $title,
        public string $path,
        public array $tags = [],
        public string $locale = 'en',
        public TranslationState $translation = TranslationState::Source,
        public bool $external = false,
        public string $docsDir = 'manual',
        public int $tocDepth = 4,
    ) {
    }

    public function isHome(): bool
    {
        return $this->slug === self::HOME;
    }
}
