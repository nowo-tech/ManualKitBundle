<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Security;

use Nowo\ManualKitBundle\Security\ManualAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ManualAccessTest extends TestCase
{
    public function testRolesAndAPublicManual(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => $attribute === 'ROLE_EDITOR');

        self::assertFalse(new ManualAccess($checker)->canRead());
        self::assertFalse(new ManualAccess($checker)->isAdmin());
        self::assertTrue(new ManualAccess($checker, 'ROLE_EDITOR')->canRead());
        self::assertTrue(new ManualAccess($checker, '', 'ROLE_EDITOR')->canRead(), 'empty role: public');
        self::assertTrue(new ManualAccess($checker, '', 'ROLE_EDITOR')->isAdmin());
    }
}
