<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;

/**
 * 1.9: a host per-chapter rule — the "install" chapter (and so its "docker" child) is off for everybody.
 */
final class NoInstallChapterAccess implements ManualChapterAccessCheckerInterface
{
    public function canReadChapter(ManualChapter $chapter): bool
    {
        return $chapter->slug !== 'install';
    }
}
