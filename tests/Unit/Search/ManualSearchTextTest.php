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

    public function testAnyAlternativeMayMatch(): void
    {
        self::assertTrue(ManualSearchText::matchesAny(['find', 'search'], 'Filter the list', ['search', 'buscar']));
        self::assertTrue(ManualSearchText::matchesAny(['cambiar contrasena', 'change password'], 'Change your Password'));
        self::assertFalse(ManualSearchText::matchesAny(['find', 'locate'], 'Filter the list', ['search']));
        self::assertFalse(ManualSearchText::matchesAny([], 'Filter the list'));
        self::assertSame([['contrasena', 'nueva'], ['x']], ManualSearchText::words([' Contraseña  NUEVA ', '  ', 'x']));
        self::assertTrue(ManualSearchText::matchesWords([['nueva'], ['contrasena']], 'Contraseña'));
    }
}
