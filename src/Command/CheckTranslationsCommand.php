<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Command;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\TranslationState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_merge;
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
#[AsCommand(name: 'nowo:manual:check', description: 'Report manifest problems, missing and outdated manual translations and missing stable anchors.')]
final readonly class CheckTranslationsCommand
{
    /**
     * Receives the translation audit and the catalog it reports on.
     */
    public function __construct(
        private TranslationAudit $audit,
        private ManualCatalog $catalog,
    ) {
    }

    /**
     * Reports manifest problems, outdated translations and missing anchors; returns a failure code in strict or sources mode when one is found.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Fail on manifest problems, outdated translations and missing anchors')]
        bool $strict = false,
        #[Option(description: 'Only check that every manifest source exists (e.g. at container start); fail on a problem')]
        bool $sources = false,
    ): int {
        // 1.3: what the manifest lists but cannot be served (the chapter is left out of the manual).
        $sourceProblems = $this->catalog->problems();
        if ($sourceProblems !== []) {
            $io->error(array_merge(['The manual manifest has problems (those chapters are not served):'], $sourceProblems));
        }
        if ($sources) {
            if ($sourceProblems === []) {
                $io->success(sprintf('%d manual chapters, every source found.', count($this->catalog->all())));
            }

            return $sourceProblems === [] ? Command::SUCCESS : Command::FAILURE;
        }
        $failed = $strict && $sourceProblems !== [];

        $rows     = [];
        $problems = 0;
        foreach ($this->audit->run() as $row) {
            $bad = $row['state'] === TranslationState::Outdated || $row['missing_anchors'] !== [];
            $problems += $bad ? 1 : 0;
            $rows[] = [$row['locale'], $row['slug'], $row['state']->value, implode(', ', $row['missing_anchors'])];
        }
        if ($rows === []) {
            $io->note('No translation locale is configured (nowo_manual_kit.locales).');

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        $io->table(['Locale', 'Chapter', 'State', 'Missing anchors'], $rows);
        if ($problems === 0) {
            $io->success(sprintf('%d chapter translations checked.', count($rows)));

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        $io->warning(sprintf('%d translation(s) outdated or missing anchors. Update them, then run nowo:manual:stamp.', $problems));

        return $strict ? Command::FAILURE : Command::SUCCESS;
    }
}
