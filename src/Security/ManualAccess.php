<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Who reads the manual (`role`) and who also reads the administrators' chapters and blocks (`admin_role`).
 * 1.9: implements {@see ManualAccessInterface}; per-chapter rules come from {@see ManualChapterAccessCheckerInterface}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualAccess implements ManualAccessInterface
{
    /**
     * Receives the authorization checker, the reader and admin roles and (1.9) the host's per-chapter rule.
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private string $role = 'ROLE_USER',
        private string $adminRole = 'ROLE_ADMIN',
        private ?ManualChapterAccessCheckerInterface $chapters = null,
    ) {
    }

    /**
     * Whether the current user may read the manual (an empty reader role makes it public).
     */
    public function canRead(): bool
    {
        return $this->role === '' || $this->authorization->isGranted($this->role);
    }

    /**
     * Whether the current user has the admin role (sees admin-only chapters and images).
     */
    public function isAdmin(): bool
    {
        return $this->authorization->isGranted($this->adminRole);
    }

    /**
     * 1.9: an admin-only chapter needs the admin role; then the host's {@see ManualChapterAccessCheckerInterface}
     * decides (allow-all by default).
     */
    public function canReadChapter(ManualChapter $chapter): bool
    {
        if ($chapter->adminOnly && !$this->isAdmin()) {
            return false;
        }

        return !$this->chapters instanceof ManualChapterAccessCheckerInterface || $this->chapters->canReadChapter($chapter);
    }
}
