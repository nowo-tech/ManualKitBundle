<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Command;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\TranslationState;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use Nowo\ManualKitBundle\Translation\UiGlossary;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function dirname;
use function in_array;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;
use function str_contains;
use function strlen;

/**
 * Machine-translates the chapters of a translation folder that are missing or outdated, then stamps them.
 *
 * A file it writes carries {@see MarkdownTranslator::MACHINE_MARKER}; a translation without the marker was edited
 * by a person and is only replaced with `--force`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'nowo:manual:translate', description: 'Machine-translate the missing or outdated chapters of a manual translation.')]
final readonly class TranslateManualCommand
{
    public function __construct(
        private ManualCatalog $catalog,
        private TranslationAudit $audit,
        private MarkdownTranslator $markdown,
        private UiGlossary $glossary,
    ) {
    }

    /**
     * @param list<string> $chapter
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Locale folder, e.g. es')]
        string $locale,
        #[Option(description: 'Only these chapter slugs')]
        array $chapter = [],
        #[Option(description: 'Also retranslate up-to-date and hand-edited translations')]
        bool $force = false,
        #[Option(description: 'List what would be translated and the characters sent, without calling the service')]
        bool $dryRun = false,
    ): int {
        if (!$this->markdown->enabled()) {
            $io->error('No machine translation provider: set nowo_manual_kit.machine_translation.provider (deepl or libretranslate).');

            return Command::FAILURE;
        }
        if (!$this->catalog->translates($locale)) {
            $io->error(sprintf('"%s" is not a translation locale of the manual.', $locale));

            return Command::FAILURE;
        }

        $labels = $this->glossary->labels($this->catalog->sourceLocale(), $locale);
        $rows   = [];
        $done   = 0;
        foreach ($this->catalog->all($locale) as $found) {
            if ($found->external || ($chapter !== [] && !in_array($found->slug, $chapter, true))) {
                continue;
            }
            $path     = $this->catalog->translationPath($found->slug, $locale);
            $existing = is_file($path) ? (string) file_get_contents($path) : null;
            if ($found->translation === TranslationState::Translated && !$force) {
                continue;
            }
            if ($existing !== null && !str_contains($existing, MarkdownTranslator::MACHINE_MARKER) && !$force) {
                $rows[] = [$found->slug, $found->translation->value, 'skipped: edited by a person (review it, or --force)'];
                continue;
            }
            $source = (string) file_get_contents($this->catalog->sourcePath($found->slug));
            if ($dryRun) {
                $rows[] = [$found->slug, $found->translation->value, sprintf('would translate (%d characters)', strlen($source))];
                continue;
            }

            $translated = $this->markdown->translate($source, $this->catalog->sourceLocale(), $locale, $labels);
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o775, true);
            }
            file_put_contents($path, MarkdownTranslator::MACHINE_MARKER . "\n" . $translated);
            $this->audit->stamp($found->slug, $locale);
            $rows[] = [$found->slug, $found->translation->value, 'translated'];
            ++$done;
        }

        if ($rows === []) {
            $io->success(sprintf('Every %s chapter is up to date.', $locale));

            return Command::SUCCESS;
        }
        $io->table(['Chapter', 'Was', 'Result'], $rows);
        $io->success(sprintf('%d chapter(s) machine-translated into %s.', $done, $locale));

        return Command::SUCCESS;
    }
}
