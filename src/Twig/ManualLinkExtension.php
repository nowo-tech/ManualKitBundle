<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Twig;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `manual_href('manual/05-admin.md#users')`: the in-app chapter when `in_app` is on and the chapter is in the
 * manifest, the published copy (`docs_url` + path) otherwise; `manual_is_external()` says which one it is.
 * `nowo_manual_layout()` and `nowo_manual_asset()` serve the bundle's own chapter template.
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
        private readonly string $assetsBase = '/bundles/nowomanualkit/',
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
            new TwigFunction('nowo_manual_asset', fn (string $path): string => $this->assetsBase . ltrim($path, '/')),
        ];
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
