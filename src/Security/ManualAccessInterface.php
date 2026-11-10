<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;

/**
 * 1.9: who may read the manual, who also reads the administrators' chapters and blocks, and which chapters a reader
 * may open. The default is {@see ManualAccess} (roles + {@see ManualChapterAccessCheckerInterface}); alias this
 * interface to your own service to replace the whole policy.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ManualAccessInterface
{
    /**
     * Whether the current user may read the manual at all.
     */
    public function canRead(): bool;

    /**
     * Whether the current user sees the administrators' chapters, blocks and images.
     */
    public function isAdmin(): bool;

    /**
     * Whether the current user may open this chapter (navigation, previous / next, sub-pages, search, images and the
     * page itself). A chapter under a parent the user may not open is hidden too. The bundle always enforces the
     * `audience: admin` rule itself (`adminOnly` needs {@see self::isAdmin()}), so an implementation only has to
     * add its own rule; it cannot widen access to admin-only chapters.
     */
    public function canReadChapter(ManualChapter $chapter): bool;
}
