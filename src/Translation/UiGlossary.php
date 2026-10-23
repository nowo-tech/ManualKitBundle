<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Translation;

use Symfony\Component\Translation\TranslatorBagInterface;

use function array_key_first;
use function arsort;
use function mb_strlen;
use function strpbrk;

/**
 * The application's own UI labels, source text => target text, from its translation catalogues: a chapter that
 * names **Settings** gets the label the translated interface shows, not a free translation of the word.
 *
 * Only short single-line messages without placeholders are kept; a source text with several translations takes the
 * most frequent one.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class UiGlossary
{
    private const int MAX_LENGTH = 60;

    /**
     * @param list<string> $domains catalogue domains to read (e.g. `messages`)
     */
    public function __construct(
        private ?TranslatorBagInterface $translator = null,
        private array $domains = ['messages'],
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function labels(string $sourceLocale, string $targetLocale): array
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            return [];
        }
        $source = $this->translator->getCatalogue($sourceLocale);
        $target = $this->translator->getCatalogue($targetLocale);

        /** @var array<string, array<string, int>> $votes */
        $votes = [];
        foreach ($this->domains as $domain) {
            foreach ($source->all($domain) as $id => $text) {
                $text = (string) $text;
                if (!$target->defines((string) $id, $domain) || !$this->isLabel($text)) {
                    continue;
                }
                $translated = $target->get((string) $id, $domain);
                if (!$this->isLabel($translated)) {
                    continue;
                }
                $votes[$text][$translated] = ($votes[$text][$translated] ?? 0) + 1;
            }
        }

        $labels = [];
        foreach ($votes as $text => $candidates) {
            arsort($candidates);
            $labels[$text] = (string) array_key_first($candidates);
        }

        return $labels;
    }

    private function isLabel(string $text): bool
    {
        return $text !== '' && mb_strlen($text) <= self::MAX_LENGTH && strpbrk($text, "%{}<>\n") === false;
    }
}
