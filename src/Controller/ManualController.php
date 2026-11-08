<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Controller;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Routing\ManualRoutes;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

use function array_values;
use function basename;
use function is_file;

/**
 * The manual inside the application: `{directory}/*.md` rendered for readers (`role`), in the request locale when a
 * translation exists; the administrators' chapters for `admin_role` only (404 for everyone else — no existence
 * oracle). Images are served from `{directory}/images` — to members only those their chapters show.
 *
 * 1.6: nested chapters are served under their parents (`/{parent}/{chapter}`, `/{grandparent}/{parent}/{chapter}`),
 * with the same access rules — a child is never more visible than its parent.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualController
{
    /**
     * Receives the catalog, renderer, access rules, Twig and router used to serve the manual pages.
     *
     * @param list<array{key: string, label: string|null, translation_domain: string}> $groups 1.8: `groups` (order and
     *                                                                                         labels of the navigation)
     */
    public function __construct(
        private ManualCatalog $catalog,
        private ManualRenderer $renderer,
        private ManualAccess $access,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $template = '@NowoManualKitBundle/chapter.html.twig',
        private array $groups = [],
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

        return new RedirectResponse(ManualRoutes::generate($this->urls, $this->readable($this->catalog->find($chapter))), Response::HTTP_MOVED_PERMANENTLY);
    }

    /** 1.6: `guide/install.md` (a link written for GitHub inside a sub-folder) redirects to the child chapter. */
    #[Route('/{parent}/{chapter}.md', name: 'nowo_manual_child_md', requirements: ['parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function childMarkdownAlias(string $parent, string $chapter): RedirectResponse
    {
        $this->requireReader();

        return new RedirectResponse(ManualRoutes::generate($this->urls, $this->nested($chapter, [$parent], null)), Response::HTTP_MOVED_PERMANENTLY);
    }

    /** 1.6: `guide/install/docker.md` redirects to the grandchild chapter. */
    #[Route('/{grandparent}/{parent}/{chapter}.md', name: 'nowo_manual_grandchild_md', requirements: ['grandparent' => ManualRoutes::SEGMENT, 'parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function grandchildMarkdownAlias(string $grandparent, string $parent, string $chapter): RedirectResponse
    {
        $this->requireReader();

        return new RedirectResponse(ManualRoutes::generate($this->urls, $this->nested($chapter, [$grandparent, $parent], null)), Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Serves a manual image; members only get images shown in a chapter they can read.
     */
    #[Route('/images/{file}', name: 'nowo_manual_image', requirements: ['file' => '[A-Za-z0-9][A-Za-z0-9._-]*\.(?:webp|png|jpe?g|gif|svg)'], methods: ['GET'], priority: 10)]
    public function image(Request $request, string $file): BinaryFileResponse
    {
        $this->requireReader();
        $path = $this->catalog->directory() . '/images/' . basename($file);
        if (!is_file($path) || (!$this->access->isAdmin() && !$this->shownToMembers(basename($file), $request->getLocale()))) {
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
        $found = $this->readable($this->catalog->find($chapter, $request->getLocale()));
        if ($found->isHome()) {
            throw new NotFoundHttpException();
        }
        if ($found->ancestors !== []) {
            // 1.6: a nested chapter lives under its parents; a link built from the slug alone still reaches it.
            return new RedirectResponse(ManualRoutes::generate($this->urls, $found), Response::HTTP_MOVED_PERMANENTLY);
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

        return $this->page($request, $this->nested($chapter, [$parent], $request->getLocale()));
    }

    /**
     * 1.6: renders a chapter two levels down (`/{grandparent}/{parent}/{chapter}`).
     */
    #[Route('/{grandparent}/{parent}/{chapter}', name: 'nowo_manual_grandchild', requirements: ['grandparent' => ManualRoutes::SEGMENT, 'parent' => ManualRoutes::SEGMENT, 'chapter' => ManualRoutes::SEGMENT], methods: ['GET'])]
    public function grandchild(Request $request, string $grandparent, string $parent, string $chapter): Response
    {
        $this->requireReader();

        return $this->page($request, $this->nested($chapter, [$grandparent, $parent], $request->getLocale()));
    }

    /**
     * A nested chapter the viewer may read, reached through its own parents only (404 otherwise: no other path to
     * it, no existence oracle).
     *
     * @param list<string> $ancestors
     */
    private function nested(string $slug, array $ancestors, ?string $locale): ManualChapter
    {
        $found = $this->readable($this->catalog->find($slug, $locale));
        if ($found->ancestors !== $ancestors) {
            throw new NotFoundHttpException();
        }

        return $found;
    }

    private function page(Request $request, ManualChapter $chapter): Response
    {
        $admin             = $this->access->isAdmin();
        $locale            = $request->getLocale();
        [$previous, $next] = $this->catalog->neighbours($chapter, $admin, $locale);

        $chapters = $this->catalog->visible($admin, $locale);

        return new Response($this->twig->render($this->template, [
            'rendered' => $this->renderer->render($chapter, $admin),
            'chapters' => $chapters,
            // 1.8: the navigation groups that have chapters, in the configured order.
            'groups'   => $this->navGroups($chapters),
            'previous' => $previous,
            'next'     => $next,
            // 1.6: the chapter's parents (breadcrumb) and the sub-pages the viewer may read.
            'ancestors' => $this->catalog->ancestors($chapter, $locale),
            'children'  => $this->catalog->children($chapter, $admin, $locale),
        ]));
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

    /** The chapter, or a 404 when it does not exist or is for administrators only (no existence oracle). */
    private function readable(?ManualChapter $chapter): ManualChapter
    {
        if (!$chapter instanceof ManualChapter || ($chapter->adminOnly && !$this->access->isAdmin())) {
            throw new NotFoundHttpException();
        }

        return $chapter;
    }

    /**
     * Whether a member's chapters show this image: screenshots of the administrators' chapters and blocks stay with
     * them. The member renderings are cached, so this costs a lookup per chapter.
     */
    private function shownToMembers(string $file, string $locale): bool
    {
        $src = '"' . $this->urls->generate('nowo_manual_image', ['file' => $file]) . '"';
        foreach ($this->catalog->visible(false, $locale) as $chapter) {
            if (str_contains($this->renderer->render($chapter, false)->html, $src)) {
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
