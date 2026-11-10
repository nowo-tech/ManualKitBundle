<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;

/**
 * 1.9: default per-chapter rule: no extra restriction (only the reader / admin roles apply).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class AllowAllManualChapterAccessChecker implements ManualChapterAccessCheckerInterface
{
    /**
     * Always true.
     */
    public function canReadChapter(ManualChapter $chapter): bool
    {
        return true;
    }
}
