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
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private string $role = 'ROLE_USER',
        private string $adminRole = 'ROLE_ADMIN',
    ) {
    }

    public function canRead(): bool
    {
        return $this->role === '' || $this->authorization->isGranted($this->role);
    }

    public function isAdmin(): bool
    {
        return $this->authorization->isGranted($this->adminRole);
    }
}
