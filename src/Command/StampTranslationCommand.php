<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Command;

use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * After updating a translation, records the source it now matches (`<!-- translation-of: … -->`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(name: 'nowo:manual:stamp', description: 'Mark a manual translation as up to date with its source.')]
final readonly class StampTranslationCommand
{
    public function __construct(
        private TranslationAudit $audit,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Locale folder, e.g. es')]
        string $locale,
        #[Argument(description: 'Chapter slug, e.g. 02-dashboard')]
        string $chapter,
    ): int {
        if (!$this->audit->stamp($chapter, $locale)) {
            $io->error(sprintf('No translation %s/%s.md.', $locale, $chapter));

            return Command::FAILURE;
        }
        $io->success(sprintf('%s/%s.md marked as up to date.', $locale, $chapter));

        return Command::SUCCESS;
    }
}
