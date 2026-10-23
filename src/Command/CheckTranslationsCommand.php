<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Command;

use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\TranslationState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function implode;
use function sprintf;

/**
 * Lists the manual's translations per locale — translated, outdated, missing — and the stable anchors a translation
 * lacks. `--strict` fails on outdated translations and missing anchors (CI); missing translations only warn.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'nowo:manual:check', description: 'Report missing and outdated manual translations and missing stable anchors.')]
final readonly class CheckTranslationsCommand
{
    public function __construct(
        private TranslationAudit $audit,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Fail on outdated translations and missing anchors')] bool $strict = false): int
    {
        $rows     = [];
        $problems = 0;
        foreach ($this->audit->run() as $row) {
            $bad = $row['state'] === TranslationState::Outdated || $row['missing_anchors'] !== [];
            $problems += $bad ? 1 : 0;
            $rows[] = [$row['locale'], $row['slug'], $row['state']->value, implode(', ', $row['missing_anchors'])];
        }
        if ($rows === []) {
            $io->note('No translation locale is configured (nowo_manual_kit.locales).');

            return Command::SUCCESS;
        }
        $io->table(['Locale', 'Chapter', 'State', 'Missing anchors'], $rows);
        if ($problems === 0) {
            $io->success(sprintf('%d chapter translations checked.', count($rows)));

            return Command::SUCCESS;
        }
        $io->warning(sprintf('%d translation(s) outdated or missing anchors. Update them, then run nowo:manual:stamp.', $problems));

        return $strict ? Command::FAILURE : Command::SUCCESS;
    }
}
