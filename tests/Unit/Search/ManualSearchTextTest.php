<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Search;

use Nowo\ManualKitBundle\Search\ManualSearchText;
use PHPUnit\Framework\TestCase;

final class ManualSearchTextTest extends TestCase
{
    public function testFoldsCaseAccentsAndSpaces(): void
    {
        self::assertSame('contrasena olvidee', ManualSearchText::fold("  Contraseña \t Olvidée "));
    }

    public function testEveryWordMustMatchTheTitleOrATag(): void
    {
        self::assertTrue(ManualSearchText::matches('contrasena', 'Account', ['password', 'contraseña']));
        self::assertTrue(ManualSearchText::matches('ACCOUNT pass', 'Account', ['password']));
        self::assertFalse(ManualSearchText::matches('account billing', 'Account', ['password']));
        self::assertFalse(ManualSearchText::matches('   ', 'Account'));
    }
}
