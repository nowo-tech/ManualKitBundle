<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Who reads the manual (`role`) and who also reads the administrators' chapters and blocks (`admin_role`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualAccess
{
    /**
     * Receives the authorization checker and the reader and admin roles.
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private string $role = 'ROLE_USER',
        private string $adminRole = 'ROLE_ADMIN',
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
}
