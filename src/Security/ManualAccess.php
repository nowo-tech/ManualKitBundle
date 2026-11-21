<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function array_diff;

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
     * decides (allow-all by default). 1.13: the admin-only rule applies to chapters that only use `member` / `admin`;
     * named audiences are checked by the bundle itself for the reader of the request's root
     * ({@see ManualAudiences::canSee()}), which this method does not know.
     */
    public function canReadChapter(ManualChapter $chapter): bool
    {
        if ($chapter->adminOnly && !$this->isAdmin() && $this->legacyAudiences($chapter)) {
            return false;
        }

        return !$this->chapters instanceof ManualChapterAccessCheckerInterface || $this->chapters->canReadChapter($chapter);
    }

    /**
     * Whether every level of the chapter only names `member` / `admin` (the 1.12 audiences).
     */
    private function legacyAudiences(ManualChapter $chapter): bool
    {
        foreach ($chapter->audienceLevels() as $level) {
            if (array_diff($level, ['member', 'admin']) !== []) {
                return false;
            }
        }

        return true;
    }
}
