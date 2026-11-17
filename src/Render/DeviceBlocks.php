<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use Dom\Element;
use Dom\Node;
use Dom\XPath;
use LogicException;

use function count;
use function implode;
use function in_array;
use function iterator_to_array;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * 1.12: turns `<!-- device: phone -->…<!-- /device -->` blocks into `div.manual-device` wrappers, parsed with the
 * audience rules ({@see CommentBlocks}). The comments do not survive the Markdown converter and the HTML sanitiser, so
 * each block is marked by a token paragraph before converting (a per-rendering random token: chapter text cannot
 * forge one) and the wrapper is built on the sanitised DOM. The content stays ordinary chapter Markdown: converted and
 * sanitised like the rest.
 *
 * Rules: only {@see DeviceViews::DEVICES} names are device blocks — any other name (`tablet`) keeps its content
 * shared and never reaches the markup; markers without their pair are dropped (the content is shared). A block holds
 * whole blocks of the chapter's top level: one whose markers end up in different elements (inside a list item) is
 * shared too. Markers inside code (fenced, a raw `<pre>`, an inline span: a sample of the syntax) stay as written.
 * A marker counts only alone on its line at column 0, so a block wraps whole top-level blocks: a marker in a quote,
 * a list item or a table cell (or after other text) is dropped and its content stays shared, in place.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceBlocks
{
    /** Attribute of every element of one view (a block wrapper, a companion figure or image, a "On this page" entry). */
    public const string ATTRIBUTE = 'data-nowo-manual-device';

    /** Class of a block's first paragraph when it is a bold label alone (`**Phone:**`, for GitHub readers). */
    public const string LABEL_CLASS = 'manual-device__label';

    private const string END = 'end';

    /** A fenced code block: up to its closing fence (same character, at least as long) or the end of the file. */
    private const string FENCE = '/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\1[`~]*[ \t]*$|\z)/ms';

    /** A raw HTML `<pre>` element: its content is a sample, like fenced code. */
    private const string PRE = '#<pre\b.*?</pre\s*>#is';

    /** A well-formed marker alone on its line, at column 0. */
    private const string OWN_LINE_MARKER = '/(?<![^\n])(<!--[ \t]*(?:device:[ \t]*[a-z]+|\/device)[ \t]*-->)(?=[ \t]*(?:\r?\n|\z))/i';

    /** An inline code span: a backtick run, text without a blank line, the same run again. */
    private const string CODE_SPAN = '/(?<!`)(`+)(?!`)(?:(?!\n[ \t]*\n).)+?(?<!`)\1(?!`)/s';

    /**
     * The Markdown with each device block between token paragraphs (`{token}phone` … `{token}end`).
     *
     * Only a marker alone on its line, at column 0 (so outside quotes, list items and tables), starts or ends a block;
     * any other marker is dropped and its content stays shared, in place.
     */
    public static function mark(string $markdown, string $token): string
    {
        // Code (fences, raw `<pre>`, inline spans: samples of the syntax) is kept as written: set aside while the
        // markers are read. The placeholders carry the per-rendering token, so chapter text cannot collide with one.
        $held      = [];
        $protected = $markdown;
        foreach ([self::FENCE, self::PRE, self::CODE_SPAN] as $pattern) {
            $protected = (string) preg_replace_callback($pattern, static function (array $code) use (&$held, $token): string {
                $held[] = $code[0];

                return "\x00" . $token . (count($held) - 1) . "\x00";
            }, $protected);
        }

        // A marker counts only alone on its line at column 0: the others are dropped (their content stays shared).
        $qualified = [];
        $protected = (string) preg_replace_callback(self::OWN_LINE_MARKER, static function (array $marker) use (&$qualified, $token): string {
            $qualified[] = $marker[1];

            return "\x00" . $token . 'Q' . (count($qualified) - 1) . "\x00";
        }, $protected);
        $protected = implode('', CommentBlocks::splitAtMarkers($protected, DeviceViews::KIND));
        $protected = (string) preg_replace_callback('/\x00' . preg_quote($token, '/') . 'Q(\d+)\x00/', static fn (array $marker): string => $qualified[(int) $marker[1]], $protected);

        $marked = CommentBlocks::replace($protected, DeviceViews::KIND, static function (string $device, string $content) use ($token): string {
            // An opening marker inside a block (blocks of one kind do not nest) is a stray one: dropped.
            $content = implode('', CommentBlocks::splitAtMarkers($content, DeviceViews::KIND));

            return DeviceViews::isDevice($device)
                ? "\n\n" . $token . $device . "\n\n" . $content . "\n\n" . $token . self::END . "\n\n"
                : $content;
        });

        $marked = implode('', CommentBlocks::splitAtMarkers($marked, DeviceViews::KIND));

        return (string) preg_replace_callback('/\x00' . preg_quote($token, '/') . '(\d+)\x00/', static fn (array $code): string => $held[(int) $code[1]] ?? $code[0], $marked);
    }

    /**
     * Replaces the token paragraphs of the sanitised body with the wrappers.
     *
     * @return array<string, string> the device of each h2–h4 id inside a wrapper
     */
    public static function wrap(Element $body, string $token): array
    {
        $markers = [];
        foreach (iterator_to_array($body->querySelectorAll('p')) as $paragraph) {
            $text = trim((string) $paragraph->textContent);
            if ($paragraph->childElementCount === 0 && str_starts_with($text, $token)) {
                $markers[] = [$paragraph, substr($text, strlen($token))];
            }
        }

        $headings = [];
        foreach ($markers as $i => [$open, $device]) {
            if (!DeviceViews::isDevice($device)) {
                continue;
            }
            $close = $markers[$i + 1][0] ?? null;
            if ($close === null || ($markers[$i + 1][1] ?? '') !== self::END || $close->parentNode !== $open->parentNode) {
                continue;
            }
            $wrapper = self::wrapper($open, $close, $device);
            foreach (iterator_to_array($wrapper->querySelectorAll('h2[id], h3[id], h4[id]')) as $heading) {
                $headings[(string) $heading->getAttribute('id')] = $device;
            }
        }
        foreach ($markers as [$paragraph]) {
            $paragraph->remove();
        }
        self::stripToken($body, $token);

        return $headings;
    }

    /**
     * Marks an element as belonging to one view.
     */
    public static function markElement(Element $element, string $device): void
    {
        $element->setAttribute(self::ATTRIBUTE, $device);
    }

    /**
     * Safety net: token text left in a text node (a marker the Markdown placed somewhere unforeseen) never reaches
     * the page.
     */
    private static function stripToken(Element $body, string $token): void
    {
        $pattern = '/' . preg_quote($token, '/') . '(?:' . implode('|', [...DeviceViews::DEVICES, self::END]) . ')/';
        foreach (iterator_to_array(new XPath($body->ownerDocument ?? throw new LogicException('A parsed element always has a document.'))->query('.//text()[contains(., ' . "'" . $token . "'" . ')]', $body)) as $text) {
            $text->nodeValue = (string) preg_replace($pattern, '', (string) $text->nodeValue);
        }
    }

    private static function wrapper(Element $open, Element $close, string $device): Element
    {
        $document = $open->ownerDocument ?? throw new LogicException('A parsed element always has a document.');
        $wrapper  = $document->createElement('div');
        $wrapper->setAttribute('class', 'manual-device manual-device--' . $device);
        self::markElement($wrapper, $device);
        while ($open->nextSibling instanceof Node && $open->nextSibling !== $close) {
            $wrapper->appendChild($open->nextSibling);
        }
        $open->before($wrapper);

        $first = $wrapper->firstElementChild;
        $label = $first?->firstElementChild;
        if ($first instanceof Element && $label instanceof Element && $first->tagName === 'P' && $first->childElementCount === 1 && in_array($label->tagName, ['STRONG', 'B'], true) && trim((string) $first->textContent) === trim((string) $label->textContent)) {
            $first->setAttribute('class', self::LABEL_CLASS);
        }

        return $wrapper;
    }
}
