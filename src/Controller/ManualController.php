<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Controller;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectMap;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectsInterface;
use Nowo\ManualKitBundle\Redirect\RedirectMapCompiler;
use Nowo\ManualKitBundle\Redirect\RedirectTarget;
use Nowo\ManualKitBundle\Render\DeviceViews;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Routing\ManualRoutes;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ReadableChapters;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

use function array_map;
use function array_search;
use function array_values;
use function basename;
use function count;
use function htmlspecialchars;
use function http_build_query;
use function is_file;
use function is_int;
use function is_string;
use function preg_match;
use function rawurlencode;
use function str_contains;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The manual inside the application: `{directory}/*.md` rendered for readers (`role`), in the request locale when a
 * translation exists; the administrators' chapters for `admin_role` only (404 for everyone else — no existence
 * oracle). Images are served from `{directory}/images` — to members only those their chapters show.
 *
 * 1.6: nested chapters are served under their parents (`/{parent}/{chapter}`, `/{grandparent}/{parent}/{chapter}`),
 * with the same access rules — a child is never more visible than its parent.
 *
 * 1.11: a chapter that moved (`nowo_manual_kit.redirects`) answers 301 to its new place, in the request locale, from
 * every route that serves a chapter (`/{chapter}`, the child routes and the `.md` aliases). The access check runs on the
 * target BEFORE the 301: a reader who may not open the target gets the same 404 as for the target itself or an unknown
 * slug, so the map adds no existence oracle and never reveals where a hidden chapter went.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualController
{
    /** 1.11: query parameter of a 301 whose old chapter has anchors mapped elsewhere (read by js/manual-anchors.js). */
    public const string MOVED_FROM = 'moved_from';

    /**
     * Receives the catalog, renderer, access rules, Twig and router used to serve the manual pages.
     *
     * @param list<array{key: string, label: string|null, translation_domain: string}> $groups 1.8: `groups` (order and
     *                                                                                         labels of the navigation)
     * @param ChapterRedirectsInterface $redirects 1.11: the chapters and anchors that moved
     * @param DeviceViews $devices 1.12: desktop / phone views
     */
    public function __construct(
        private ManualCatalogInterface $catalog,
        private ManualRendererInterface $renderer,
        private ManualAccessInterface $access,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $template = '@NowoManualKitBundle/chapter.html.twig',
        private array $groups = [],
        private ChapterRedirectsInterface $redirects = new ChapterRedirectMap(),
        private DeviceViews $devices = new DeviceViews(),
    ) {
    }

    /**
     * Renders the manual home page for a reader.
     */
    #[Route('', name: 'nowo_manual_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->requireReader();

        return $this->page($request, $this->readable($this->catalog->find(ManualChapter::HOME, $request->getLocale())));
    }

    /** Links written for GitHub (`04-projects.md#filters`) keep working: the `.md` form redirects. */
    #[Route('/{chapter}.md', name: 'nowo_manual_chapter_md', requirements: ['chapter' => '[A-Za-z0-9-]+'], methods: ['GET'])]
    public function markdownAlias(string $chapter): RedirectResponse
    {
        $this->requireReader();
        $found = $this->catalog->find($chapter);
        if (!$found instanceof ManualChapter) {
            // 1.11: `old-slug.md` of a chapter that moved goes straight to its new place (one hop).
            return $this->moved($chapter, null);
        }

        return $this->permanent(ManualRoutes::generate($this->urls, $this->readable($found)));
    }

    /** 1.6: `guide/install.md` (a link written for GitHub inside a sub-folder) redirects to the child chapter. */
    #[Route('/{parent}/{chapter}.md', name: 'nowo_manual_child_md', requirements: ['parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function childMarkdownAlias(string $parent, string $chapter): RedirectResponse
    {
        $this->requireReader();
        $found = $this->nested($chapter, [$parent], null);

        return $found instanceof ManualChapter ? $this->permanent(ManualRoutes::generate($this->urls, $found)) : $found;
    }

    /** 1.6: `guide/install/docker.md` redirects to the grandchild chapter. */
    #[Route('/{grandparent}/{parent}/{chapter}.md', name: 'nowo_manual_grandchild_md', requirements: ['grandparent' => ManualRoutes::SEGMENT, 'parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function grandchildMarkdownAlias(string $grandparent, string $parent, string $chapter): RedirectResponse
    {
        $this->requireReader();
        $found = $this->nested($chapter, [$grandparent, $parent], null);

        return $found instanceof ManualChapter ? $this->permanent(ManualRoutes::generate($this->urls, $found)) : $found;
    }

    /**
     * Serves a manual image; members only get images shown in a chapter they can read. 1.9: when the host's
     * per-chapter rule refuses a chapter, admins and members alike only get images shown in a chapter they can open.
     */
    #[Route('/images/{file}', name: 'nowo_manual_image', requirements: ['file' => '[A-Za-z0-9][A-Za-z0-9._-]*\.(?:webp|png|jpe?g|gif|svg)'], methods: ['GET'], priority: 10)]
    public function image(Request $request, string $file): BinaryFileResponse
    {
        $this->requireReader();
        $path = $this->catalog->directory() . '/images/' . basename($file);
        if (!is_file($path) || !$this->imageReadable(basename($file), $request->getLocale())) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, Response::HTTP_OK, [], false, null, true, true);
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // An SVG must never run script in the application's origin.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");

        return $response;
    }

    /**
     * Renders one manual chapter (the home chapter is only served by index()).
     */
    #[Route('/{chapter}', name: 'nowo_manual_chapter', requirements: ['chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function chapter(Request $request, string $chapter): Response
    {
        $this->requireReader();
        $found = $this->catalog->find($chapter, $request->getLocale());
        if (!$found instanceof ManualChapter) {
            return $this->moved($chapter, $request->getLocale());
        }
        $found = $this->readable($found);
        if ($found->isHome()) {
            throw new NotFoundHttpException();
        }
        if ($found->ancestors !== []) {
            // 1.6: a nested chapter lives under its parents; a link built from the slug alone still reaches it.
            return $this->permanent(ManualRoutes::generate($this->urls, $found));
        }

        return $this->page($request, $found);
    }

    /**
     * 1.6: renders a chapter that sits under a top-level one (`/{parent}/{chapter}`).
     */
    #[Route('/{parent}/{chapter}', name: 'nowo_manual_child', requirements: ['parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function child(Request $request, string $parent, string $chapter): Response
    {
        $this->requireReader();
        $found = $this->nested($chapter, [$parent], $request->getLocale());

        return $found instanceof ManualChapter ? $this->page($request, $found) : $found;
    }

    /**
     * 1.6: renders a chapter two levels down (`/{grandparent}/{parent}/{chapter}`).
     */
    #[Route('/{grandparent}/{parent}/{chapter}', name: 'nowo_manual_grandchild', requirements: ['grandparent' => ManualRoutes::SEGMENT, 'parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function grandchild(Request $request, string $grandparent, string $parent, string $chapter): Response
    {
        $this->requireReader();
        $found = $this->nested($chapter, [$grandparent, $parent], $request->getLocale());

        return $found instanceof ManualChapter ? $this->page($request, $found) : $found;
    }

    /**
     * A nested chapter the viewer may read, reached through its own parents only (404 otherwise: no other path to
     * it, no existence oracle). 1.11: a chapter that moved, or one reached through a parent that moved, answers 301
     * to its place.
     *
     * @param list<string> $ancestors
     */
    private function nested(string $slug, array $ancestors, ?string $locale): ManualChapter|RedirectResponse
    {
        $chapter = $this->catalog->find($slug, $locale);
        if (!$chapter instanceof ManualChapter) {
            return $this->moved($slug, $locale);
        }
        $found = $this->readable($chapter);
        if ($found->ancestors !== $ancestors) {
            foreach ($ancestors as $ancestor) {
                if ($this->redirects->chapterTarget($ancestor) instanceof RedirectTarget && !$this->catalog->find($ancestor) instanceof ManualChapter) {
                    return $this->permanent(ManualRoutes::generate($this->urls, $found));
                }
            }

            throw new NotFoundHttpException();
        }

        return $found;
    }

    /**
     * 1.11: the 301 of a chapter that moved, to the final target in one hop and in the request locale (the router
     * keeps `_locale`). The target goes through the same checks as a request for it, before the redirect: a reader
     * who may not open it gets its 404. A slug that is neither a chapter nor redirected is a 404 too.
     */
    private function moved(string $slug, ?string $locale): RedirectResponse
    {
        $target = $this->redirects->chapterTarget($slug);
        if (!$target instanceof RedirectTarget) {
            throw new NotFoundHttpException();
        }
        // A chain stops at the first chapter that still exists (an existing chapter wins over a rule for its slug).
        $walked  = RedirectMapCompiler::resolvePosition($slug, $this->redirects->rules(), fn (string $candidate): bool => $this->catalog->find($candidate) instanceof ManualChapter);
        $stopped = $walked !== null && $walked !== $target->toString();
        if ($stopped) {
            $target = RedirectTarget::fromString($walked);
        }
        $chapter = $this->readable($this->catalog->find($target->slug, $locale));
        $url     = ManualRoutes::generate($this->urls, $chapter);
        if (!$stopped && $this->redirects->hasMovedAnchors($slug)) {
            // The browser keeps the reader's #fragment (no fragment in Location); js/manual-anchors.js maps it.
            $url .= '?' . http_build_query([self::MOVED_FROM => $slug]);
        } elseif ($target->anchor !== null && $this->hasAnchor($this->renderer->render($chapter, $this->access->isAdmin()), $target->anchor)) {
            // Only an anchor the reader's rendering has: the id of an admin block never reaches a member.
            $url .= '#' . rawurlencode($target->anchor);
        }

        return $this->permanent($url);
    }

    /**
     * A 301 of the manual. Every one depends on who asks (the access check runs first), so no shared cache may keep
     * it, with or without a session.
     */
    private function permanent(string $url): RedirectResponse
    {
        $response = new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }

    private function hasAnchor(RenderedManualChapter $rendered, string $anchor): bool
    {
        return str_contains($rendered->html, ' id="' . htmlspecialchars($anchor, ENT_QUOTES | ENT_HTML5) . '"');
    }

    /**
     * 1.11: the anchor map of a page for js/manual-anchors.js — `moved`: the anchors of the old chapter named by
     * `?moved_from` (only when it really redirects here), `own`: the sections of this chapter that moved. Targets the
     * viewer may not open are left out, and so is an entry whose target anchor the viewer's rendering lacks (an admin
     * block, a translation without the stable anchor): the map reveals nothing a reader could not reach.
     *
     * @return array{moved?: array<string, string>, own?: array<string, string>}
     */
    private function anchorRedirects(Request $request, RenderedManualChapter $rendered, bool $admin): array
    {
        $chapter = $rendered->chapter;
        $from    = $request->query->all()[self::MOVED_FROM] ?? null;
        $moved   = is_string($from) && preg_match(ManualCatalog::SLUG, $from) === 1 ? $this->anchorUrls($this->redirects->movedAnchors($chapter->slug, $from), $rendered, $admin, $request->getLocale()) : [];
        $own     = $this->anchorUrls($this->redirects->ownAnchors($chapter->slug), $rendered, $admin, $request->getLocale());

        return $moved === [] && $own === [] ? [] : ['moved' => $moved, 'own' => $own];
    }

    /**
     * @param array<string, RedirectTarget> $targets
     *
     * @return array<string, string>
     */
    private function anchorUrls(array $targets, RenderedManualChapter $current, bool $admin, string $locale): array
    {
        $urls = [];
        foreach ($targets as $anchor => $target) {
            $chapter = $target->slug === $current->chapter->slug ? $current->chapter : $this->catalog->find($target->slug, $locale);
            if (!$chapter instanceof ManualChapter || !$this->canOpen($chapter)) {
                continue;
            }
            $url = ManualRoutes::generate($this->urls, $chapter);
            if ($target->anchor !== null) {
                if (!$this->hasAnchor($chapter === $current->chapter ? $current : $this->renderer->render($chapter, $admin), $target->anchor)) {
                    continue;
                }
                $url .= '#' . rawurlencode($target->anchor);
            } elseif ($chapter === $current->chapter) {
                // Already here: reloading the same page would only lose the reader's place.
                continue;
            }
            $urls[(string) $anchor] = $url;
        }

        return $urls;
    }

    private function page(Request $request, ManualChapter $chapter): Response
    {
        $admin   = $this->access->isAdmin();
        $locale  = $request->getLocale();
        $visible = $this->catalog->visible($admin, $locale);
        // 1.9: the host's per-chapter rule (ManualAccessInterface::canReadChapter) on top of the admin-only rule.
        $chapters          = ReadableChapters::filter($visible, $visible, $this->access);
        [$previous, $next] = $this->neighbours($chapter, $chapters);
        $rendered          = $this->renderer->render($chapter, $admin);

        return new Response($this->twig->render($this->template, [
            'rendered' => $rendered,
            'chapters' => $chapters,
            // 1.8: the navigation groups that have chapters, in the configured order.
            'groups'   => $this->navGroups($chapters),
            'previous' => $previous,
            'next'     => $next,
            // 1.6: the chapter's parents (breadcrumb) and the sub-pages the viewer may read.
            'ancestors' => $this->catalog->ancestors($chapter, $locale),
            'children'  => ReadableChapters::filter($this->catalog->children($chapter, $admin, $locale), $visible, $this->access),
            // 1.11: the anchors that moved, for js/manual-anchors.js (empty: no script, no attribute).
            'anchor_redirects' => $this->anchorRedirects($request, $rendered, $admin),
            // 1.12: the Desktop / Phone switch (null: none — device views off or no device content).
            'device_views' => $this->deviceViews($request, $rendered),
        ]));
    }

    /**
     * 1.12: what the page needs for the Desktop / Phone switch, or null when device views are off or the chapter has
     * no device content. `initial` is the view a search hit asks for (`?device=`), only from the allow-list.
     *
     * @return array{breakpoint: int, initial: string|null}|null
     */
    private function deviceViews(Request $request, RenderedManualChapter $rendered): ?array
    {
        if (!$this->devices->enabled || !$rendered->deviceViews) {
            return null;
        }
        $initial = $request->query->all()[DeviceViews::QUERY] ?? null;

        return ['breakpoint' => $this->devices->breakpoint, 'initial' => DeviceViews::isDevice($initial) ? $initial : null];
    }

    /**
     * 1.8: the groups of these chapters: the configured ones first (in `groups` order), then the others in the order
     * they first appear. Groups without a chapter the viewer may read are left out.
     *
     * @param list<ManualChapter> $chapters
     *
     * @return list<array{key: string, label: string|null, translation_domain: string}>
     */
    private function navGroups(array $chapters): array
    {
        $present = [];
        foreach ($chapters as $chapter) {
            $present[$chapter->group] ??= ['key' => $chapter->group, 'label' => null, 'translation_domain' => 'messages'];
        }

        $groups = [];
        foreach ($this->groups as $group) {
            if (isset($present[$group['key']])) {
                $groups[] = $group;
                unset($present[$group['key']]);
            }
        }

        return [...$groups, ...array_values($present)];
    }

    /**
     * The chapters before and after `$chapter` among those the viewer may open.
     *
     * @param list<ManualChapter> $chapters
     *
     * @return array{0: ManualChapter|null, 1: ManualChapter|null}
     */
    private function neighbours(ManualChapter $chapter, array $chapters): array
    {
        $index = array_search($chapter->slug, array_map(static fn (ManualChapter $candidate): string => $candidate->slug, $chapters), true);

        return is_int($index) ? [$chapters[$index - 1] ?? null, $chapters[$index + 1] ?? null] : [null, null];
    }

    /**
     * The chapter, or a 404 when it does not exist, is for administrators only or (1.9) is refused by the host's
     * per-chapter rule, itself or through a parent (no existence oracle).
     */
    private function readable(?ManualChapter $chapter): ManualChapter
    {
        if (!$chapter instanceof ManualChapter || !$this->canOpen($chapter)) {
            throw new NotFoundHttpException();
        }

        return $chapter;
    }

    /**
     * Whether the viewer may open the chapter: the admin-only rule, the host's per-chapter rule, on it and every parent.
     */
    private function canOpen(ManualChapter $chapter): bool
    {
        // The admin-only rule is always enforced here, whatever ManualAccessInterface implementation is aliased
        // (audit KN-M-L2); canReadChapter() adds the host's rule.
        if (($chapter->adminOnly && !$this->access->isAdmin()) || !$this->access->canReadChapter($chapter)) {
            return false;
        }
        foreach ($this->catalog->ancestors($chapter) as $ancestor) {
            if (!$this->access->canReadChapter($ancestor)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a member's chapters show this image: screenshots of the administrators' chapters and blocks stay with
     * them. The member renderings are cached, so this costs a lookup per chapter.
     */
    private function imageReadable(string $file, string $locale): bool
    {
        $admin    = $this->access->isAdmin();
        $visible  = $this->catalog->visible($admin, $locale);
        $readable = ReadableChapters::filter($visible, $visible, $this->access);
        if ($admin && count($readable) === count($visible)) {
            // Nothing refused by the host rule: administrators get every image (as before 1.9).
            return true;
        }

        // Audit KN-M-L1: only images shown in a chapter the viewer may open, rendered for their audience.
        $src = '"' . $this->urls->generate('nowo_manual_image', ['file' => $file]) . '"';
        foreach ($readable as $chapter) {
            if (str_contains($this->renderer->render($chapter, $admin)->html, $src)) {
                return true;
            }
        }

        return false;
    }

    private function requireReader(): void
    {
        if (!$this->access->canRead()) {
            // The security exception, so the firewall can ask an anonymous visitor to sign in.
            throw new AccessDeniedException();
        }
    }
}
