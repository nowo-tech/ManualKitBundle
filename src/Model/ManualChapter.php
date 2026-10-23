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
    ) {
    }

    public function isHome(): bool
    {
        return $this->slug === self::HOME;
    }
}
