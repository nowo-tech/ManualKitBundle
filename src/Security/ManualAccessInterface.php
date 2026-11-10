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
     * Whether the current user may open this chapter (navigation, previous / next, sub-pages, search and the page
     * itself). A chapter under a parent the user may not open is hidden too.
     */
    public function canReadChapter(ManualChapter $chapter): bool;
}
