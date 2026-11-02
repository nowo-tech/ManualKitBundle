<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Model;

use function count;
use function implode;

/**
 * One chapter of the manual (`{directory}/{slug}.md`, listed in `manifest.yaml`), resolved for one locale.
 *
 * 1.6: a chapter may sit under another one (manifest `parent`, at most {@see self::MAX_DEPTH} levels). `ancestors`
 * lists the slugs from the top-level chapter down to the parent, `ancestorTitles` their titles in the same locale, and
 * `folder` the sub-folder of the manual its file lives in (`` at the root, `guide/install` for a grandchild).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualChapter
{
    /** The manual's home page (the `README.md` of the directory). */
    public const string HOME = 'README';

    /** 1.6: chapter → child → grandchild. */
    public const int MAX_DEPTH = 3;

    /**
     * @param list<string> $tags search keywords and synonyms (manifest `tags`), in any locale
     * @param bool $external 1.2: a Markdown file outside the manual folder (manifest `file`), never translated
     * @param string $docsDir 1.2: the folder of the file, relative to the docs root (`manual`, or `` for
     *                        docs/CHANGELOG.md): where its relative links to other docs start
     * @param int $tocDepth 1.2: deepest heading level listed in "On this page" (manifest `toc_depth`)
     * @param list<string> $ancestors 1.6: slugs from the top-level chapter down to the parent ([] at the top level)
     * @param list<string> $ancestorTitles 1.6: the titles of `ancestors`, in the same order and locale
     * @param string $folder 1.6: sub-folder of the manual the file lives in (`` = the manual root)
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
        public array $ancestors = [],
        public array $ancestorTitles = [],
        public string $folder = '',
    ) {
    }

    /**
     * 1.6: the slug of the chapter this one sits under, or null at the top level.
     */
    public function parent(): ?string
    {
        return $this->ancestors === [] ? null : $this->ancestors[count($this->ancestors) - 1];
    }

    /**
     * 1.6: 1 for a top-level chapter, 2 for a child, 3 for a grandchild.
     */
    public function depth(): int
    {
        return count($this->ancestors) + 1;
    }

    /**
     * 1.6: the URL path of the chapter below the manual prefix (`guide/install/docker`), or `` for the home page.
     */
    public function urlPath(): string
    {
        return $this->isHome() ? '' : implode('/', [...$this->ancestors, $this->slug]);
    }

    /**
     * 1.6: the title with its ancestors (`Guide › Install › Docker`), the title alone at the top level.
     */
    public function trailTitle(string $separator = ' › '): string
    {
        return implode($separator, [...$this->ancestorTitles, $this->title]);
    }

    /**
     * Whether this chapter is the manual home page.
     */
    public function isHome(): bool
    {
        return $this->slug === self::HOME;
    }
}
