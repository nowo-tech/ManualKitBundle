<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Translation;

use LogicException;

use function array_chunk;
use function array_filter;
use function array_key_last;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function html_entity_decode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function ltrim;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Machine-translates a manual chapter and keeps it a valid chapter of the translation folder.
 *
 * Only prose goes to the service — one text per paragraph, heading, list item or table cell. Left as they are:
 * fenced code, `<a id>` anchors, HTML comments (audiences, tags), table separators and HTML lines; inside a text,
 * inline code, link and image targets, CommonMark attributes, inline HTML and URLs travel as `<x i="N"/>`
 * placeholders. Relative link targets get the `../` the translation folder needs. A bold UI label that the
 * application translates (**Settings**, or a path **Administration → Users**) takes the label of the translated
 * interface ({@see UiGlossary}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MarkdownTranslator
{
    /** Marks a file written by `nowo:manual:translate`; remove it once a person has reviewed the translation. */
    public const string MACHINE_MARKER = '<!-- machine-translated -->';

    private const int BATCH = 40;

    private const string KEEP_LINE = '/^\s*(?:$|<a\s+id="[^"]*"\s*><\/a>\s*$|<!--.*-->\s*$|\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)*\|?\s*$|<\/?[A-Za-z][^>]*>\s*$|(?:-{3,}|\*{3,}|_{3,})\s*$)/';

    private const string PREFIX = '/^(\s*(?:>\s?)*(?:#{1,6}\s+|(?:[-*+]|\d+[.)])\s+(?:\[[ xX]\]\s+)?)?)(.*)$/';

    /**
     * Uses the machine translator, when one is configured, to translate the text segments.
     */
    public function __construct(
        private ?MachineTranslatorInterface $translator = null,
    ) {
    }

    /**
     * Whether a machine translator is configured.
     */
    public function enabled(): bool
    {
        return $this->translator instanceof MachineTranslatorInterface;
    }

    /**
     * @param array<string, string> $glossary source UI label => target UI label
     */
    public function translate(string $markdown, string $sourceLocale, string $targetLocale, array $glossary = []): string
    {
        if (!$this->translator instanceof MachineTranslatorInterface) {
            throw new LogicException('No machine translation provider is configured (nowo_manual_kit.machine_translation.provider).');
        }

        /** @var list<list<int|string>|string> $lines a kept line, or literal parts and segment numbers */
        $lines = [];
        /** @var list<string> $segments */
        $segments = [];
        $fence    = null;
        $open     = false; // the previous line is prose a plain next line continues
        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $line) {
            if ($fence !== null) {
                $lines[] = $line;
                if (str_starts_with(ltrim($line), $fence)) {
                    $fence = null;
                }
                continue;
            }
            if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $match) === 1) {
                $fence   = $match[1];
                $lines[] = $line;
                $open    = false;
                continue;
            }
            if (preg_match(self::KEEP_LINE, $line) === 1) {
                $lines[] = $line;
                $open    = false;
                continue;
            }
            if (str_starts_with(ltrim($line), '|')) {
                $parts = [];
                foreach (preg_split('/((?<!\\\\)\|)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $cell) {
                    if ($cell === '|' || trim($cell) === '') {
                        $parts[] = $cell;
                        continue;
                    }
                    $parts[]    = ' ';
                    $parts[]    = count($segments);
                    $parts[]    = ' ';
                    $segments[] = trim($cell);
                }
                $lines[] = $parts;
                $open    = false;
                continue;
            }
            preg_match(self::PREFIX, $line, $match);
            [$prefix, $text] = [$match[1] ?? '', $match[2] ?? $line];
            $last            = array_key_last($segments);
            if (trim($prefix) === '' && $open && $last !== null) {
                $segments[$last] .= ' ' . trim($text);
                continue;
            }
            $lines[]    = [$prefix, count($segments)];
            $segments[] = $text;
            $open       = !str_contains($prefix, '#');
        }

        $translated = $this->translateSegments($this->translator, $segments, $sourceLocale, $targetLocale, $glossary);

        return implode("\n", array_map(static fn (string|array $line): string => is_array($line)
            ? implode('', array_map(static fn (string|int $part): string => is_int($part) ? $translated[$part] : $part, $line))
            : $line, $lines));
    }

    /**
     * @param list<string> $segments
     * @param array<string, string> $glossary
     *
     * @return list<string>
     */
    private function translateSegments(MachineTranslatorInterface $translator, array $segments, string $sourceLocale, string $targetLocale, array $glossary): array
    {
        $protected = [];
        $texts     = [];
        foreach ($segments as $i => $segment) {
            $protected[$i] = [];
            $texts[]       = $this->protect($segment, $glossary, $protected[$i]);
        }

        // A text with no words outside its placeholders (a cell holding only `code`) is not sent.
        $send    = array_keys(array_filter($texts, static fn (string $text): bool => preg_match('/\p{L}/u', (string) preg_replace('/<[^>]*>|&\w+;/', '', $text)) === 1));
        $results = $texts;
        foreach (array_chunk($send, self::BATCH) as $batch) {
            $translated = $translator->translate(array_map(static fn (int $i): string => $texts[$i], $batch), $sourceLocale, $targetLocale);
            foreach ($batch as $n => $i) {
                $results[$i] = $translated[$n] ?? $texts[$i];
            }
        }

        $out = [];
        foreach ($results as $i => $result) {
            $out[] = $this->restore($result, $protected[$i]);
        }

        return $out;
    }

    /**
     * @param array<string, string> $glossary
     * @param list<string> $kept the protected spans, by placeholder number
     */
    private function protect(string $text, array $glossary, array &$kept): string
    {
        $keep = static function (string $span) use (&$kept): string {
            $kept[] = $span;

            return "\x01" . (count($kept) - 1) . "\x02";
        };

        // Inline code, then link / image targets (relative ones get `../`), CommonMark attributes, HTML and URLs.
        $text = (string) preg_replace_callback('/(`+)(?!`).+?(?<!`)\1(?!`)/', static fn (array $m): string => $keep($m[0]), $text);
        $text = (string) preg_replace_callback('/\]\(([^()\s]*(?:\([^()\s]*\)[^()\s]*)*)(\s+"[^"]*")?\)/', static fn (array $m): string => ']' . $keep('(' . self::relink($m[1]) . ($m[2] ?? '') . ')'), $text);
        $text = (string) preg_replace_callback('/\{[.#][^}\n]*\}|\{[A-Za-z-]+=[^}\n]*\}/', static fn (array $m): string => $keep($m[0]), $text);
        $text = (string) preg_replace_callback('/<!--.*?-->|<\/?[A-Za-z][^>\n]*>|https?:\/\/[^\s<>\x01]*[^\s<>\x01.,;:!?)\]]/', static fn (array $m): string => $keep($m[0]), $text);

        // Bold UI labels the application translates.
        $text = (string) preg_replace_callback('/\*\*([^*\x01]+?)\*\*/', static function (array $m) use ($glossary, $keep): string {
            $parts  = explode(' → ', $m[1]);
            $labels = array_map(static fn (string $part): ?string => $glossary[trim($part)] ?? null, $parts);

            return in_array(null, $labels, true) ? $m[0] : $keep('**' . implode(' → ', $labels) . '**');
        }, $text);

        $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = (string) preg_replace('/\*\*(.+?)\*\*|__(.+?)__/', '<b>$1$2</b>', $text);
        $text = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])|(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/', '<i>$1$2</i>', $text);
        $text = (string) preg_replace('/~~(.+?)~~/', '<s>$1</s>', $text);

        return (string) preg_replace('/\x01(\d+)\x02/', '<x i="$1"/>', $text);
    }

    /**
     * @param list<string> $kept
     */
    private function restore(string $xml, array $kept): string
    {
        $xml = (string) preg_replace('/<b>(\s*)(.*?)(\s*)<\/b>/s', '$1**$2**$3', $xml);
        $xml = (string) preg_replace('/<i>(\s*)(.*?)(\s*)<\/i>/s', '$1*$2*$3', $xml);
        $xml = (string) preg_replace('/<s>(\s*)(.*?)(\s*)<\/s>/s', '$1~~$2~~$3', $xml);

        $used = [];
        $out  = '';
        foreach (preg_split('/(<x\s+i="\d+"\s*\/>|<x\s+i="\d+"\s*>\s*<\/x>)/', $xml, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if (preg_match('/^<x\s+i="(\d+)"/', $part, $m) === 1) {
                $used[(int) $m[1]] = true;
                $out .= $kept[(int) $m[1]] ?? '';
                continue;
            }
            $out .= html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        // A span the service dropped is not lost: it goes at the end of the text.
        foreach ($kept as $i => $span) {
            if (!isset($used[$i])) {
                $out .= ' ' . $span;
            }
        }

        return trim($out);
    }

    /**
     * A relative target, seen from the translation folder one level down.
     */
    private static function relink(string $target): string
    {
        return $target === '' || preg_match('/^(?:#|\/|[A-Za-z][A-Za-z0-9+.-]*:)/', $target) === 1 ? $target : '../' . $target;
    }
}
