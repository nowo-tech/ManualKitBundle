<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use ArrayObject;
use Dom\Element;
use Dom\HTMLDocument;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use LogicException;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;
use Nowo\ManualKitBundle\Routing\ManualRoutes;
use Nowo\ManualKitBundle\Search\ManualSearchText;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_key_exists;
use function bin2hex;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function random_bytes;
use function realpath;
use function str_starts_with;
use function strlen;
use function substr;

use const ENT_HTML5;
use const ENT_QUOTES;
use const LIBXML_NOERROR;
use const PREG_SET_ORDER;

/**
 * Renders one manual chapter to HTML the way GitHub does (GFM, the same heading anchors), with the links rewritten
 * for the app: chapter → the chapter route (`#anchor` kept), `images/x.webp` → the image route, other repository docs
 * → `docs_url`. `<!-- audience: admin -->` blocks and links to admin chapters are dropped for members, the result is
 * sanitised, and images become figures (zoom link, title as caption, portrait screenshots sized like the desktop ones,
 * several images of one paragraph side by side). `{.class width=…}` after an image (CommonMark attributes) styles it.
 *
 * Stable anchors: `<a id="summary"></a>` right before a heading (its own line, then a blank line) or inside it gives
 * the heading that id in every language, so the app's links survive translated titles; GitHub honours the same anchor.
 *
 * 1.12 ({@see DeviceViews}, off by default): `<!-- device: phone|desktop -->` blocks become `div.manual-device`
 * wrappers, an image with a phone companion (`x.phone.webp`) gets a phone twin, and the TOC entries of a device block
 * carry its `device`. Disabled, the output is the 1.11 one byte for byte.
 *
 * Cached per chapter, audience, locale and file version.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualRenderer implements ManualRendererInterface
{
    /** `<!-- audience: admin -->…<!-- /audience -->` (administrators only) or `audience: github` (repository readers only). */
    private const string AUDIENCE = 'audience';

    /** Bumped when the rendered markup or the cached object changes, so cached chapters are rebuilt. */
    private const string VERSION = '3';

    /** A file of the shared images directory (the `nowo_manual_image` route requirement). */
    private const string IMAGE_FILE = '/^[A-Za-z0-9][A-Za-z0-9._-]*\.(?:webp|png|jpe?g|gif|svg)\z/i';

    /** 1.12: set on a phone companion until the figures are built (never in the output). */
    private const string COMPANION = 'data-nowo-manual-companion';

    /** Taller than wide: a phone screenshot. */
    public const string PORTRAIT = 'manual-img--portrait';

    /**
     * Receives the catalog, router and cache used to render chapters, plus the external docs URL and (1.12) the
     * desktop / phone views configuration.
     */
    public function __construct(
        private ManualCatalogInterface $catalog,
        private UrlGeneratorInterface $urls,
        private CacheInterface $cache,
        private string $docsUrl = '',
        private DeviceViews $devices = new DeviceViews(),
    ) {
    }

    /**
     * Renders a chapter to HTML (cached per chapter, locale, translation state and audience).
     */
    public function render(ManualChapter $chapter, bool $admin): RenderedManualChapter
    {
        // A host catalog (ManualCatalogInterface) may serve chapters without a manifest.yaml in its directory.
        $manifest = $this->catalog->directory() . '/' . ManualCatalog::MANIFEST;
        $images   = $this->catalog->directory() . '/images';
        $key      = 'nowo_manual.' . hash('xxh128', implode('|', [
            self::VERSION,
            $chapter->slug,
            $chapter->locale,
            $chapter->translation->value,
            $admin ? 'admin' : 'member',
            (string) filemtime($chapter->path),
            (string) filesize($chapter->path),
            is_file($manifest) ? (string) filemtime($manifest) : 'no-manifest',
            $this->urls->getContext()->getBaseUrl(),
            $this->devices->enabled ? 'devices' . $this->devices->imageSuffix . '|' . (is_dir($images) ? (string) filemtime($images) : 'no-images') : 'no-devices',
        ]));

        // @igor-ignore - the cache calls the closure synchronously; nothing outlives this call
        return $this->cache->get($key, function (ItemInterface $item) use ($chapter, $admin): RenderedManualChapter {
            $item->expiresAfter(86400);

            return $this->build($chapter, $admin);
        });
    }

    /**
     * Keeps the `admin` blocks for administrators, drops them for members, and always drops the `github` blocks
     * (screenshot regeneration, demo credentials: for people reading the repository, never in the app).
     *
     * Fails closed: a marker left without its pair — a block never closed, a typo in the opening tag — hides the rest
     * of the chapter from members instead of showing them what it was guarding.
     */
    public static function audienceFiltered(string $markdown, bool $admin): string
    {
        $filtered = CommentBlocks::replace($markdown, self::AUDIENCE, static fn (string $audience, string $content): string => $audience === 'admin' && $admin ? $content : '');
        $stray    = CommentBlocks::splitAtMarkers($filtered, self::AUDIENCE);
        if (count($stray) === 1) {
            return $filtered;
        }

        return $admin ? implode('', $stray) : $stray[0];
    }

    private function build(ManualChapter $chapter, bool $admin): RenderedManualChapter
    {
        $visible  = [];
        $chapters = [];
        $files    = [];
        foreach ($this->catalog->all() as $candidate) {
            $visible[$candidate->slug]  = $admin || !$candidate->adminOnly;
            $chapters[$candidate->slug] = $candidate;
            if (!$candidate->external) {
                // 1.6: `manual/guide/install.md` → install (what a relative link resolves to, from the docs root).
                $files[$candidate->docsDir . '/' . $candidate->slug . '.md'] = $candidate->slug;
                if ($candidate->ancestors !== [] && $candidate->folder === '') {
                    // Kept at the manual root: `guide/x.md` still names it (plain text when it is hidden).
                    $files[$candidate->docsDir . '/' . implode('/', $candidate->ancestors) . '/' . $candidate->slug . '.md'] = $candidate->slug;
                }
            }
        }

        $environment = new Environment([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'apply_id_to_heading' => true,
                'id_prefix'           => '',
                'fragment_prefix'     => '',
                'html_class'          => 'manual-anchor',
                'insert'              => 'after',
                'symbol'              => '#',
                'title'               => 'Link to this section',
                'min_heading_level'   => 2,
                'max_heading_level'   => 4,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->addExtension(new AttributesExtension());
        /** @var ArrayObject<string, string> $images 1.12: the `src` of each manual image → its file */
        $images = new ArrayObject();
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($visible, $chapter, $chapters, $files, $images): void {
            $this->rewrite($event, new LinkContext($visible, $chapters, $files, $chapter), $images);
        }, -10);

        $markdown = self::audienceFiltered((string) file_get_contents($chapter->path), $admin);
        // The chapter title is the page heading: drop the Markdown's own `# Title` line (after a translation marker).
        $markdown = (string) preg_replace('/\A(?:\s*<!--[^\n]*-->\s*\n)*# [^\n]*\n/', '', $markdown);
        // 1.12: a random token per rendering marks the device blocks (chapter text cannot forge a wrapper).
        $token = $this->devices->enabled ? 'nowomanualdevice' . bin2hex(random_bytes(12)) . 'x' : null;
        if ($token !== null) {
            $markdown = DeviceBlocks::mark($markdown, $token);
        }
        [$html, $devices, $views] = $this->document($this->sanitizer()->sanitize((string) new MarkdownConverter($environment)->convert($markdown)), $token, $images);

        return new RenderedManualChapter($chapter, $html, $this->toc($html, $this->sectionTags($markdown), $chapter->tocDepth, $devices), $views);
    }

    /**
     * @param ArrayObject<string, string> $images
     */
    private function rewrite(DocumentParsedEvent $event, LinkContext $context, ArrayObject $images): void
    {
        // Collected first: unlinking detaches nodes, which would break a live walk.
        foreach (iterator_to_array($event->getDocument()->iterator(), false) as $node) {
            if ($node instanceof Image) {
                $this->image($node, $images);

                continue;
            }
            if ($node instanceof Link) {
                $this->link($node, $context);
            }
        }
    }

    /**
     * @param ArrayObject<string, string> $images
     */
    private function image(Image $node, ArrayObject $images): void
    {
        $file = $this->imageFile($node->getUrl());
        if ($file !== null) {
            $src = $this->urls->generate('nowo_manual_image', ['file' => $file]);
            $node->setUrl($src);
            $images[$src] = $file;
            $size         = $this->imageSize($file);
            if (is_array($size)) {
                $node->data->set('attributes/width', (string) $size[0]);
                $node->data->set('attributes/height', (string) $size[1]);
                if ($size[1] > $size[0]) {
                    $node->data->set('attributes/class', trim($node->data->get('attributes/class', '') . ' ' . self::PORTRAIT));
                }
            }
        }
        $node->data->set('attributes/loading', 'lazy');
        $node->data->set('attributes/decoding', 'async');
    }

    private function link(Link $node, LinkContext $context): void
    {
        $visible = $context->visible;
        $docsDir = $context->chapter->docsDir;
        $url     = $node->getUrl();
        if ($url === '' || str_starts_with($url, '#')) {
            return;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            $node->data->set('attributes/target', '_blank');
            $node->data->set('attributes/rel', 'noopener noreferrer');

            return;
        }
        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $fragment          = $fragment === null || $fragment === '' ? '' : '#' . $fragment;
        // Where the link points, from the docs root (1.2: an external chapter's links start from its own folder).
        $target = $this->normalise(($docsDir === '' ? '' : $docsDir . '/') . $path);
        // `02-dashboard.md`, `./02-dashboard.md` or, from a translation folder, `../02-dashboard.md`; from an external
        // chapter, the manual file's path (`manual/02-dashboard.md`).
        $slug = null;
        if (preg_match('#^(?:\./|\.\./)?([A-Za-z0-9-]+)\.md$#', (string) $path, $match) === 1 && $docsDir === basename($this->catalog->directory())) {
            $slug = $match[1];
        } elseif (preg_match('#^' . preg_quote(basename($this->catalog->directory()), '#') . '/([A-Za-z0-9-]+)\.md$#', $target, $match) === 1) {
            $slug = $match[1];
        }
        $slug ??= $this->nestedSlug((string) $path, $target, $context);
        if ($slug !== null && array_key_exists($slug, $visible)) {
            if (!$visible[$slug]) {
                // A link to an admin chapter reads as plain text for members.
                foreach ($node->children() as $child) {
                    $node->insertBefore($child);
                }
                $node->detach();

                return;
            }
            $node->setUrl(ManualRoutes::generate($this->urls, $context->chapters[$slug]) . $fragment);

            return;
        }
        // Anything else in the repository (../PRODUCTION.md, a manual file not served): the published copy.
        $node->setUrl($this->docsUrl . $target . $fragment);
        $node->data->set('attributes/target', '_blank');
        $node->data->set('attributes/rel', 'noopener noreferrer');
    }

    /**
     * 1.6: the chapter a relative link of the tree points at — a file of a sub-folder (`guide/install.md`,
     * `../faq.md`), the same link read from a translation's own folder (`es/guide/…`), or a bare `install.md` written
     * in a nested chapter. Null for anything else.
     */
    private function nestedSlug(string $path, string $target, LinkContext $context): ?string
    {
        if (isset($context->files[$target])) {
            return $context->files[$target];
        }
        $chapter = $context->chapter;
        if ($chapter->external) {
            return null;
        }
        if ($chapter->locale !== $this->catalog->sourceLocale()) {
            $root       = basename($this->catalog->directory());
            $translated = $this->normalise($root . '/' . $chapter->locale . ($chapter->folder === '' ? '' : '/' . $chapter->folder) . '/' . $path);
            $prefix     = $root . '/' . $chapter->locale . '/';
            // `es/guide/x.md` stands for `guide/x.md`; `../../faq.md` from `es/guide/` already reaches the source file.
            $source = str_starts_with($translated, $prefix) ? $root . '/' . substr($translated, strlen($prefix)) : $translated;
            if (isset($context->files[$source])) {
                return $context->files[$source];
            }
        }
        if ($chapter->folder !== '' && preg_match('#^(?:\./)?([A-Za-z0-9-]+)\.md$#', $path, $match) === 1 && isset($context->chapters[$match[1]])) {
            return $match[1];
        }

        return null;
    }

    /**
     * A file's width and height, or false (a vector image has no intrinsic size to reserve; PHP 8.5 getimagesize()
     * reads SVG too).
     *
     * @return array{0: int, 1: int}|false
     */
    private function imageSize(string $file): array|false
    {
        $size = str_ends_with(strtolower($file), '.svg') ? false : @getimagesize($this->catalog->directory() . '/images/' . $file);

        return is_array($size) ? [$size[0], $size[1]] : false;
    }

    /**
     * DOM pass on the sanitised HTML: stable heading anchors, (1.12) device wrappers and phone companions, then
     * figures for paragraphs made only of images.
     *
     * @param ArrayObject<string, string> $images
     *
     * @return array{0: string, 1: array<string, string>|null, 2: bool} the HTML; with device views on, the device of
     *                                                                  each heading id inside a device block; whether
     *                                                                  the chapter has device content
     */
    private function document(string $html, ?string $token, ArrayObject $images): array
    {
        if ($token === null && !str_contains($html, '<img') && !str_contains($html, '<a id=')) {
            return [$html, null, false];
        }
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);
        $body     = $document->body ?? throw new LogicException('The HTML parser always builds a body.');
        $this->stableAnchors($body);
        $devices = null;
        $views   = false;
        if ($token !== null) {
            $devices = DeviceBlocks::wrap($body, $token);
            $this->companions($body, $images);
            $views = $body->querySelector('[' . DeviceBlocks::ATTRIBUTE . ']') instanceof Element;
        }
        foreach (iterator_to_array($body->querySelectorAll('p')) as $paragraph) {
            $found = [];
            foreach ($paragraph->childNodes as $child) {
                if ($child instanceof Element && $child->tagName === 'IMG') {
                    $found[] = $child;
                } elseif (trim((string) $child->textContent) !== '' || $child instanceof Element) {
                    continue 2;
                }
            }
            if ($found !== []) {
                // 1.12: a phone companion takes the place of its desktop image, never a place of its own.
                $places = count(array_filter($found, static fn (Element $image): bool => !$image->hasAttribute(self::COMPANION)));
                $paragraph->replaceWith(...($places === 1 ? array_map(fn (Element $image): Element => $this->figure($document, $image), $found) : [$this->gallery($document, $found)]));
            }
        }
        foreach (iterator_to_array($body->querySelectorAll('[' . self::COMPANION . ']')) as $companion) {
            $companion->removeAttribute(self::COMPANION);
        }

        return [$body->innerHTML, $devices, $views];
    }

    /**
     * 1.12: a manual image with a phone companion (`x.phone.webp` next to `x.webp`): in a phone block the companion
     * replaces it; in shared content the image is the desktop view and a twin with the companion the phone one.
     *
     * @param ArrayObject<string, string> $images
     */
    private function companions(Element $body, ArrayObject $images): void
    {
        foreach (iterator_to_array($body->querySelectorAll('img')) as $image) {
            $file   = $images[(string) $image->getAttribute('src')] ?? null;
            $device = $image->closest('[' . DeviceBlocks::ATTRIBUTE . ']')?->getAttribute(DeviceBlocks::ATTRIBUTE);
            $phone  = $file === null || $device === DeviceViews::DESKTOP ? null : $this->companionFile($file);
            if ($phone === null) {
                continue;
            }
            if ($device === DeviceViews::PHONE) {
                $this->useImage($image, $phone);

                continue;
            }
            $twin = $image->cloneNode(true);
            if (!$twin instanceof Element) {
                continue; // @codeCoverageIgnore
            }
            $this->useImage($twin, $phone);
            $twin->setAttribute(self::COMPANION, '');
            DeviceBlocks::markElement($image, DeviceViews::DESKTOP);
            DeviceBlocks::markElement($twin, DeviceViews::PHONE);
            $image->after($twin);
        }
    }

    /**
     * Points an image at another file of the images directory, with that file's size and orientation.
     */
    private function useImage(Element $image, string $file): void
    {
        $image->setAttribute('src', $this->urls->generate('nowo_manual_image', ['file' => $file]));
        $classes = array_filter(explode(' ', (string) $image->getAttribute('class')), static fn (string $class): bool => $class !== '' && $class !== self::PORTRAIT);
        $size    = $this->imageSize($file);
        if (is_array($size)) {
            $image->setAttribute('width', (string) $size[0]);
            $image->setAttribute('height', (string) $size[1]);
            if ($size[1] > $size[0]) {
                $classes[] = self::PORTRAIT;
            }
        } else {
            $image->removeAttribute('width');
            $image->removeAttribute('height');
        }
        if ($classes === []) {
            $image->removeAttribute('class');
        } else {
            $image->setAttribute('class', implode(' ', $classes));
        }
    }

    /**
     * 1.12: the phone companion of a manual image when that file exists inside the manual's images directory (never
     * through a symbolic link out of it), else null.
     */
    private function companionFile(string $file): ?string
    {
        $name = $this->devices->companion($file);
        if ($name === null || preg_match(self::IMAGE_FILE, $name) !== 1) {
            return null;
        }
        $directory = realpath($this->catalog->directory() . '/images');
        $path      = $directory === false ? false : realpath($directory . '/' . $name);

        return $path !== false && is_file($path) && str_starts_with($path, $directory . '/') ? $name : null;
    }

    /**
     * `<a id="x"></a>` inside a heading, or alone right before it, becomes the heading's id (and its permalink).
     */
    private function stableAnchors(Element $body): void
    {
        foreach (iterator_to_array($body->querySelectorAll('a[id]')) as $anchor) {
            if (trim((string) $anchor->textContent) !== '') {
                continue;
            }
            $heading = $anchor->closest('h2, h3, h4');
            $holder  = $anchor;
            if ($heading === null) {
                // Alone on its line: the parser wraps it in a paragraph (or keeps it bare) before the heading.
                $holder  = $anchor->parentElement?->tagName === 'P' && $anchor->parentElement->childElementCount === 1 && trim((string) $anchor->parentElement->textContent) === '' ? $anchor->parentElement : $anchor;
                $next    = $holder->nextElementSibling;
                $heading = $next !== null && in_array($next->tagName, ['H2', 'H3', 'H4'], true) ? $next : null;
            }
            if ($heading === null) {
                continue;
            }
            $id = (string) $anchor->getAttribute('id');
            $heading->setAttribute('id', $id);
            $permalink = $heading->querySelector('a.manual-anchor');
            $permalink?->setAttribute('href', '#' . $id);
            $holder->remove();
        }
    }

    /**
     * @param list<Element> $images
     */
    private function gallery(HTMLDocument $document, array $images): Element
    {
        $gallery = $document->createElement('div');
        $gallery->setAttribute('class', 'manual-gallery');
        foreach ($images as $image) {
            $gallery->appendChild($this->figure($document, $image));
        }

        return $gallery;
    }

    private function figure(HTMLDocument $document, Element $image): Element
    {
        $figure = $document->createElement('figure');
        $figure->setAttribute('class', trim('manual-figure' . (str_contains((string) $image->getAttribute('class'), self::PORTRAIT) ? ' manual-figure--portrait' : '')));
        // 1.12: a desktop / phone image: the whole figure belongs to that view.
        $device = $image->getAttribute(DeviceBlocks::ATTRIBUTE);
        if ($device !== null) {
            $image->removeAttribute(DeviceBlocks::ATTRIBUTE);
            DeviceBlocks::markElement($figure, $device);
        }
        $zoom = $document->createElement('a');
        $zoom->setAttribute('class', 'manual-zoom');
        $zoom->setAttribute('href', (string) $image->getAttribute('src'));
        $caption = trim((string) $image->getAttribute('title'));
        $image->removeAttribute('title');
        $zoom->appendChild($image);
        $figure->appendChild($zoom);
        if ($caption !== '') {
            $figcaption              = $document->createElement('figcaption');
            $figcaption->textContent = $caption;
            $figure->appendChild($figcaption);
        }

        return $figure;
    }

    /**
     * `images/x.webp` (source), `../images/x.webp` (a translation folder) or, 1.6, `../../images/x.webp` from a
     * sub-folder (up to three levels up) — the shared images directory.
     */
    private function imageFile(string $url): ?string
    {
        return preg_match('#^(?:\./|(?:\.\./){1,3})?images/([A-Za-z0-9][A-Za-z0-9._-]*\.(?:webp|png|jpe?g|gif|svg))$#i', $url, $match) === 1 ? $match[1] : null;
    }

    /**
     * `manual/../product/SMS.md` → `product/SMS.md` (relative to the documentation root).
     */
    private function normalise(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts) . (str_ends_with($path, '/') ? '/' : '');
    }

    /**
     * @param array<string, list<string>> $sectionTags
     * @param array<string, string>|null $devices 1.12: the device of the headings inside a device block
     *
     * @return list<array{level: int, id: string, text: string, tags: list<string>, device?: string}>
     */
    private function toc(string $html, array $sectionTags, int $depth = 4, ?array $devices = null): array
    {
        preg_match_all('#<h([234])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)</h\1>#s', $html, $matches, PREG_SET_ORDER);
        $toc = [];
        foreach ($matches as [, $level, $id, $inner]) {
            if ((int) $level > $depth) {
                continue;
            }
            $text  = trim(html_entity_decode(strip_tags((string) preg_replace('#<a\b[^>]*class="manual-anchor"[^>]*>.*?</a>#s', '', $inner)), ENT_QUOTES | ENT_HTML5));
            $entry = ['level' => (int) $level, 'id' => $id, 'text' => $text, 'tags' => $sectionTags[ManualSearchText::fold($text)] ?? []];
            if (isset($devices[$id])) {
                // 1.12: the search opens this view; the chapter page shows the entry with it only.
                $entry['device'] = $devices[$id];
            }
            $toc[] = $entry;
        }

        return $toc;
    }

    /**
     * `<!-- tags: password, contraseña -->` right under a `##` / `###` / `####` heading (invisible on GitHub) — keyed by the
     * folded heading text, Markdown emphasis, code marks and inline anchors removed.
     *
     * @return array<string, list<string>>
     */
    private function sectionTags(string $markdown): array
    {
        preg_match_all('/^#{2,4} +([^\n]+?) *\n(?:[ \t]*\n)*<!--\s*tags:\s*(.*?)\s*-->/m', $markdown, $matches, PREG_SET_ORDER);
        $tags = [];
        foreach ($matches as [, $heading, $list]) {
            $title                                = (string) preg_replace('/<a\s+id="[^"]*"\s*><\/a>/', '', str_replace(['**', '__', '`', '*'], '', $heading));
            $tags[ManualSearchText::fold($title)] = array_values(array_filter(array_map(trim(...), explode(',', $list)), static fn (string $tag): bool => $tag !== ''));
        }

        return $tags;
    }

    private function sanitizer(): HtmlSanitizer
    {
        $config = new HtmlSanitizerConfig()
            ->allowSafeElements()
            ->allowElement('kbd')
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowAttribute('id', ['h2', 'h3', 'h4', 'a'])
            ->allowAttribute('aria-hidden', ['a'])
            // Symfony 8.2 html-sanitizer no longer allows tabindex by default; the heading permalink needs tabindex="-1".
            ->allowAttribute('tabindex', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('class', ['a', 'img'])
            ->allowAttribute('title', ['img'])
            ->allowAttribute('loading', ['img'])
            ->allowAttribute('decoding', ['img'])
            ->allowAttribute('width', ['img'])
            ->allowAttribute('height', ['img'])
            ->withMaxInputLength(-1);

        return new HtmlSanitizer($config);
    }
}
