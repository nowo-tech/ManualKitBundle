<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Translation;

use Nowo\ManualKitBundle\Translation\UiGlossary;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class UiGlossaryTest extends TestCase
{
    public function testShortLabelsWithTheMostFrequentTranslation(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'nav.settings'     => 'Settings',
            'project.settings' => 'Settings',
            'admin.settings'   => 'Settings',
            'nav.users'        => 'Users',
            'hello'            => 'Hello %name%',
            'long'             => str_repeat('a', 61),
            'untranslated'     => 'Only English',
            'html'             => 'Fine',
        ], 'en');
        $translator->addResource('array', [
            'nav.settings'     => 'Ajustes',
            'project.settings' => 'Configuración',
            'admin.settings'   => 'Configuración',
            'nav.users'        => 'Usuarios',
            'hello'            => 'Hola %name%',
            'long'             => 'b',
            'html'             => '<b>Bien</b>',
        ], 'es');
        $translator->addResource('array', ['other' => 'Other'], 'en', 'forms');
        $translator->addResource('array', ['other' => 'Otro'], 'es', 'forms');

        self::assertSame(['Settings' => 'Configuración', 'Users' => 'Usuarios'], new UiGlossary($translator)->labels('en', 'es'));
        self::assertSame(['Other' => 'Otro'], new UiGlossary($translator, ['forms'])->labels('en', 'es'));
    }

    public function testWithoutACatalogueTranslatorThereIsNoGlossary(): void
    {
        self::assertSame([], new UiGlossary()->labels('en', 'es'));
    }
}
