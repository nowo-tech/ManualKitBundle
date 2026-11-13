<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Twig;

use LogicException;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Routing\ManualRoutes;
use Override;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_callable;
use function is_string;

/**
 * `manual_href('manual/05-admin.md#users')`: the in-app chapter when `in_app` is on and the chapter is in the
 * manifest, the published copy (`docs_url` + path) otherwise; `manual_is_external()` says which one it is.
 * `manual_chapter_path(chapter)` (1.6) is a chapter's URL by its place in the tree and
 * `nowo_manual_breadcrumbs_template()` (1.6) the breadcrumb partial (UiKit's when installed).
 * `nowo_manual_layout()`, `nowo_manual_asset()` and `nowo_manual_asset_package()` serve the bundle's own chapter
 * template; published files resolve through the `nowo_manual_kit` asset package (REQ-ASSETS-004), and
 * `nowo_manual_standalone_lightbox()` says whether the template loads the self-contained lightbox script.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualLinkExtension extends AbstractExtension
{
    /** 1.6: the breadcrumb partial used without UiKit. */
    public const string BREADCRUMBS = '@NowoManualKitBundle/_breadcrumbs.html.twig';

    /**
     * Receives the catalog, router and link settings used by the manual Twig functions.
     */
    public function __construct(
        private readonly ManualCatalogInterface $catalog,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $docsUrl = '',
        private readonly bool $inApp = true,
        private readonly string $layout = '@NowoManualKitBundle/layout.html.twig',
        private readonly ?Packages $packages = null,
        private readonly bool $standaloneLightbox = false,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('manual_href', $this->href(...)),
            new TwigFunction('manual_is_external', $this->isExternal(...)),
            new TwigFunction('manual_chapter_path', $this->chapterPath(...)),
            new TwigFunction('nowo_manual_breadcrumbs_template', self::breadcrumbsTemplate(...), ['needs_environment' => true]),
            new TwigFunction('nowo_manual_layout', fn (): string => $this->layout),
            new TwigFunction('nowo_manual_asset', $this->asset(...)),
            new TwigFunction('nowo_manual_asset_package', static fn (): string => Configuration::ALIAS),
            new TwigFunction('nowo_manual_standalone_lightbox', fn (): bool => $this->standaloneLightbox),
        ];
    }

    /**
     * URL of a file the bundle publishes (`css/manual.css`), through the `nowo_manual_kit` asset package — the same as
     * `asset('css/manual.css', 'nowo_manual_kit')`.
     */
    public function asset(string $path): string
    {
        if (!$this->packages instanceof Packages) {
            throw new LogicException('nowo_manual_asset() needs the Asset component: keep framework.assets enabled.');
        }

        return $this->packages->getUrl(ltrim($path, '/'), Configuration::ALIAS);
    }

    /**
     * 1.6: the URL of a chapter by its place in the tree (`/manual/guide/install` for a child; the index for the home
     * page) — what templates print instead of `path('nowo_manual_chapter', {chapter: …})`.
     */
    public function chapterPath(ManualChapter $chapter): string
    {
        return ManualRoutes::generate($this->urls, $chapter);
    }

    /**
     * 1.6: the template of a nested chapter's breadcrumb — UiKit's `breadcrumbs` partial (page contract, themed once
     * by the host) when UiKit is installed, the bundle's own partial with the same markup otherwise.
     */
    public static function breadcrumbsTemplate(Environment $twig): string
    {
        $partial  = $twig->getFunction('nowo_ui_partial');
        $callable = $partial?->getCallable();
        $template = is_callable($callable) ? $callable('breadcrumbs', 'NowoManualKitBundle') : null;

        return is_string($template) ? $template : self::BREADCRUMBS;
    }

    /**
     * Returns the in-app URL of a manual document link, or the external docs URL when it is not served in the app.
     */
    public function href(string $doc): string
    {
        return $this->inAppPath($doc) ?? $this->docsUrl . $doc;
    }

    /**
     * Whether a manual document link points outside the app.
     */
    public function isExternal(string $doc): bool
    {
        return $this->inAppPath($doc) === null;
    }

    private function inAppPath(string $doc): ?string
    {
        // 1.6: `manual/guide/install.md` (the file of a nested chapter, up to two folders down) too.
        if (!$this->inApp || preg_match('/^manual\/((?:[A-Za-z0-9-]+\/){0,2})([A-Za-z0-9-]+)\.md(#[A-Za-z0-9_-]*)?$/', $doc, $match) !== 1) {
            return null;
        }
        $chapter = $this->catalog->find($match[2]);
        if (!$chapter instanceof ManualChapter || ($match[1] !== '' && $chapter->folder . '/' !== $match[1])) {
            return null;
        }

        return ManualRoutes::generate($this->urls, $chapter) . ($match[3] ?? '');
    }
}
