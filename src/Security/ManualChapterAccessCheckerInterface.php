<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;

/**
 * 1.9: a per-chapter rule of the host (plan, tenant, feature flag…), checked by {@see ManualAccess} after the
 * built-in admin-only rule. The default {@see AllowAllManualChapterAccessChecker} lets every reader open every chapter
 * their role allows (the behaviour before 1.9). Alias this interface to your own service:
 *
 *     Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface: '@App\Help\PlanChapterAccess'
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ManualChapterAccessCheckerInterface
{
    /**
     * Whether the current user may open this chapter. Use plain values of the chapter (`slug`, `group`, `tags`,
     * `ancestors`, `adminOnly`).
     */
    public function canReadChapter(ManualChapter $chapter): bool;
}
