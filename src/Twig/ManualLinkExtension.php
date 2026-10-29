<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Twig;

use LogicException;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Override;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `manual_href('manual/05-admin.md#users')`: the in-app chapter when `in_app` is on and the chapter is in the
 * manifest, the published copy (`docs_url` + path) otherwise; `manual_is_external()` says which one it is.
 * `nowo_manual_layout()`, `nowo_manual_asset()` and `nowo_manual_asset_package()` serve the bundle's own chapter
 * template; published files resolve through the `nowo_manual_kit` asset package (REQ-ASSETS-004), and
 * `nowo_manual_standalone_lightbox()` says whether the template loads the self-contained lightbox script.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ManualLinkExtension extends AbstractExtension
{
    /**
     * Receives the catalog, router and link settings used by the manual Twig functions.
     */
    public function __construct(
        private readonly ManualCatalog $catalog,
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
        if (!$this->inApp || preg_match('/^manual\/([A-Za-z0-9-]+)\.md(#[A-Za-z0-9_-]*)?$/', $doc, $match) !== 1) {
            return null;
        }
        $chapter = $this->catalog->find($match[1]);
        if (!$chapter instanceof ManualChapter) {
            return null;
        }

        return ($chapter->isHome()
            ? $this->urls->generate('nowo_manual_index')
            : $this->urls->generate('nowo_manual_chapter', ['chapter' => $chapter->slug])) . ($match[2] ?? '');
    }
}
