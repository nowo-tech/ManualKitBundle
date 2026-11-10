<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * 1.9 (audit KN-M-L2): a host access policy that forgets the admin-only rule in canReadChapter().
 */
final readonly class PermissiveAccessPolicy implements ManualAccessInterface
{
    public function __construct(private Security $security)
    {
    }

    public function canRead(): bool
    {
        return $this->security->isGranted('ROLE_USER');
    }

    public function isAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    public function canReadChapter(ManualChapter $chapter): bool
    {
        return true;
    }
}
