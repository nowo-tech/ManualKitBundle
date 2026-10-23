<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Controller;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

use function basename;
use function is_file;

/**
 * The manual inside the application: `{directory}/*.md` rendered for readers (`role`), in the request locale when a
 * translation exists; the administrators' chapters for `admin_role` only (404 for everyone else — no existence
 * oracle). Images are served from `{directory}/images` — to members only those their chapters show.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsController]
final readonly class ManualController
{
    public function __construct(
        private ManualCatalog $catalog,
        private ManualRenderer $renderer,
        private ManualAccess $access,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $template = '@NowoManualKitBundle/chapter.html.twig',
    ) {
    }

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
        $found = $this->readable($this->catalog->find($chapter));

        return new RedirectResponse($found->isHome()
            ? $this->urls->generate('nowo_manual_index')
            : $this->urls->generate('nowo_manual_chapter', ['chapter' => $found->slug]), Response::HTTP_MOVED_PERMANENTLY);
    }

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

    #[Route('/{chapter}', name: 'nowo_manual_chapter', requirements: ['chapter' => '[A-Za-z0-9][A-Za-z0-9-]*'], methods: ['GET'])]
    public function chapter(Request $request, string $chapter): Response
    {
        $this->requireReader();
        $found = $this->readable($this->catalog->find($chapter, $request->getLocale()));
        if ($found->isHome()) {
            throw new NotFoundHttpException();
        }

        return $this->page($request, $found);
    }

    private function page(Request $request, ManualChapter $chapter): Response
    {
        $admin             = $this->access->isAdmin();
        $locale            = $request->getLocale();
        [$previous, $next] = $this->catalog->neighbours($chapter, $admin, $locale);

        return new Response($this->twig->render($this->template, [
            'rendered' => $this->renderer->render($chapter, $admin),
            'chapters' => $this->catalog->visible($admin, $locale),
            'previous' => $previous,
            'next'     => $next,
        ]));
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
