<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

/**
 * 1.11 port: the chapters and anchors that moved (`old-slug` → `new-slug`, `old-slug#anchor` → `new-slug#anchor`).
 *
 * The default is {@see ChapterRedirectMap}, built once per container from `nowo_manual_kit.redirects` and the
 * manifest's `redirects:` file; alias this interface to your own service to read the map from elsewhere. A
 * replacement must stay immutable for the life of the process (FrankenPHP worker mode), return internal slugs only
 * and answer with the final target (chains already collapsed, no cycles).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ChapterRedirectsInterface
{
    /**
     * Where a chapter that no longer exists went (the final hop), or null when the slug is not redirected.
     */
    public function chapterTarget(string $slug): ?RedirectTarget;

    /**
     * Whether readers arriving from this old chapter need the client-side anchor map (`?moved_from=<slug>` is added to
     * the 301), because some of its anchors went somewhere else than the same anchor of the new chapter.
     */
    public function hasMovedAnchors(string $slug): bool;

    /**
     * The anchors of the old chapter `$from` mapped for a reader who reached `$page` through its redirect
     * (`''` = no fragment), or [] when `$from` does not redirect to `$page` with anchors.
     *
     * @return array<string, RedirectTarget>
     */
    public function movedAnchors(string $page, string $from): array;

    /**
     * The anchors of the existing chapter `$page` that moved (sections taken to another chapter or renamed), applied
     * on that page when the fragment is not on it.
     *
     * @return array<string, RedirectTarget>
     */
    public function ownAnchors(string $page): array;

    /**
     * The map as written (old → new, before chains are collapsed), for `nowo:manual:redirects`.
     *
     * @return array<string, string>
     */
    public function rules(): array;
}
