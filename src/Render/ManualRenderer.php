<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

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
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;
use Nowo\ManualKitBundle\Search\ManualSearchText;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_key_exists;
use function count;
use function implode;
use function in_array;
use function is_array;

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
 * Cached per chapter, audience, locale and file version.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualRenderer
{
    /** `<!-- audience: admin -->…<!-- /audience -->` (administrators only) or `audience: github` (repository readers only). */
    private const string AUDIENCE_BLOCK = '/<!--\s*audience:\s*([a-z]+)\s*-->(.*?)<!--\s*\/audience\s*-->/si';
    /** Any `audience` comment still there after the balanced blocks went: an unclosed or malformed marker. */
    private const string STRAY_AUDIENCE_MARKER = '/<!--\s*\/?\s*audience.*?-->/si';

    /** Bumped when the rendered markup changes, so cached chapters are rebuilt. */
    private const string VERSION = '1';

    /** Taller than wide: a phone screenshot. */
    public const string PORTRAIT = 'manual-img--portrait';

    public function __construct(
        private ManualCatalog $catalog,
        private UrlGeneratorInterface $urls,
        private CacheInterface $cache,
        private string $docsUrl = '',
    ) {
    }

    public function render(ManualChapter $chapter, bool $admin): RenderedManualChapter
    {
        $key = 'nowo_manual.' . hash('xxh128', implode('|', [
            self::VERSION,
            $chapter->slug,
            $chapter->locale,
            $chapter->translation->value,
            $admin ? 'admin' : 'member',
            (string) filemtime($chapter->path),
            (string) filesize($chapter->path),
            (string) filemtime($this->catalog->directory() . '/' . ManualCatalog::MANIFEST),
            $this->urls->getContext()->getBaseUrl(),
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
        $filtered = (string) preg_replace_callback(
            self::AUDIENCE_BLOCK,
            static fn (array $block): string => strtolower($block[1]) === 'admin' && $admin ? $block[2] : '',
            $markdown,
        );
        $stray = preg_split(self::STRAY_AUDIENCE_MARKER, $filtered);
        if ($stray === false || count($stray) === 1) {
            return $filtered;
        }

        return $admin ? implode('', $stray) : $stray[0];
    }

    private function build(ManualChapter $chapter, bool $admin): RenderedManualChapter
    {
        $visible = [];
        foreach ($this->catalog->all() as $candidate) {
            $visible[$candidate->slug] = $admin || !$candidate->adminOnly;
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
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($visible): void {
            $this->rewrite($event, $visible);
        }, -10);

        $markdown = self::audienceFiltered((string) file_get_contents($chapter->path), $admin);
        // The chapter title is the page heading: drop the Markdown's own `# Title` line (after a translation marker).
        $markdown = (string) preg_replace('/\A(?:\s*<!--[^\n]*-->\s*\n)*# [^\n]*\n/', '', $markdown);
        $html     = $this->document($this->sanitizer()->sanitize((string) new MarkdownConverter($environment)->convert($markdown)));

        return new RenderedManualChapter($chapter, $html, $this->toc($html, $this->sectionTags($markdown)));
    }

    /**
     * @param array<string, bool> $visible chapter slug => readable by this viewer
     */
    private function rewrite(DocumentParsedEvent $event, array $visible): void
    {
        // Collected first: unlinking detaches nodes, which would break a live walk.
        foreach (iterator_to_array($event->getDocument()->iterator(), false) as $node) {
            if ($node instanceof Image) {
                $this->image($node);

                continue;
            }
            if ($node instanceof Link) {
                $this->link($node, $visible);
            }
        }
    }

    private function image(Image $node): void
    {
        $file = $this->imageFile($node->getUrl());
        if ($file !== null) {
            $node->setUrl($this->urls->generate('nowo_manual_image', ['file' => $file]));
            $size = @getimagesize($this->catalog->directory() . '/images/' . $file);
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

    /**
     * @param array<string, bool> $visible
     */
    private function link(Link $node, array $visible): void
    {
        $url = $node->getUrl();
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
        // `02-dashboard.md`, `./02-dashboard.md` or, from a translation folder, `../02-dashboard.md`.
        if (preg_match('#^(?:\./|\.\./)?([A-Za-z0-9-]+)\.md$#', (string) $path, $match) === 1 && array_key_exists($match[1], $visible)) {
            if (!$visible[$match[1]]) {
                // A link to an admin chapter reads as plain text for members.
                foreach ($node->children() as $child) {
                    $node->insertBefore($child);
                }
                $node->detach();

                return;
            }
            $node->setUrl(($match[1] === ManualChapter::HOME
                ? $this->urls->generate('nowo_manual_index')
                : $this->urls->generate('nowo_manual_chapter', ['chapter' => $match[1]])) . $fragment);

            return;
        }
        // Anything else in the repository (../PRODUCTION.md, a manual file not served): the published copy.
        $node->setUrl($this->docsUrl . $this->normalise('manual/' . $path) . $fragment);
        $node->data->set('attributes/target', '_blank');
        $node->data->set('attributes/rel', 'noopener noreferrer');
    }

    /**
     * DOM pass on the sanitised HTML: stable heading anchors, then figures for paragraphs made only of images.
     */
    private function document(string $html): string
    {
        if (!str_contains($html, '<img') && !str_contains($html, '<a id=')) {
            return $html;
        }
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);
        $body     = $document->body ?? throw new LogicException('The HTML parser always builds a body.');
        $this->stableAnchors($body);
        foreach (iterator_to_array($body->querySelectorAll('p')) as $paragraph) {
            $images = [];
            foreach ($paragraph->childNodes as $child) {
                if ($child instanceof Element && $child->tagName === 'IMG') {
                    $images[] = $child;
                } elseif (trim((string) $child->textContent) !== '' || $child instanceof Element) {
                    continue 2;
                }
            }
            if ($images !== []) {
                $paragraph->replaceWith(count($images) === 1 ? $this->figure($document, $images[0]) : $this->gallery($document, $images));
            }
        }

        return $body->innerHTML;
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

    /** `images/x.webp` (source) or `../images/x.webp` (a translation folder) — the shared images directory. */
    private function imageFile(string $url): ?string
    {
        return preg_match('#^(?:\./|\.\./)?images/([A-Za-z0-9][A-Za-z0-9._-]*\.(?:webp|png|jpe?g|gif|svg))$#i', $url, $match) === 1 ? $match[1] : null;
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
     *
     * @return list<array{level: int, id: string, text: string, tags: list<string>}>
     */
    private function toc(string $html, array $sectionTags): array
    {
        preg_match_all('#<h([234])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)</h\1>#s', $html, $matches, PREG_SET_ORDER);
        $toc = [];
        foreach ($matches as [, $level, $id, $inner]) {
            $text  = trim(html_entity_decode(strip_tags((string) preg_replace('#<a\b[^>]*class="manual-anchor"[^>]*>.*?</a>#s', '', $inner)), ENT_QUOTES | ENT_HTML5));
            $toc[] = ['level' => (int) $level, 'id' => $id, 'text' => $text, 'tags' => $sectionTags[ManualSearchText::fold($text)] ?? []];
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
