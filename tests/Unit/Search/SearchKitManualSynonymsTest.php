<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Search;

use Nowo\ManualKitBundle\Search\SearchKitManualSynonyms;
use Nowo\SearchKitBundle\Contract\SearchSynonymProviderInterface;
use Nowo\SearchKitBundle\Search\Synonym\SynonymExpander;
use PHPUnit\Framework\TestCase;

/**
 * 1.15: the manual's own search gets the alternatives SearchKit's expander builds from its synonym providers.
 */
final class SearchKitManualSynonymsTest extends TestCase
{
    public function testAlternativesAreTheExpandersTexts(): void
    {
        $provider = new class implements SearchSynonymProviderInterface {
            public function synonymsOf(string $word, string $locale): array
            {
                return match ([$word, $locale]) {
                    ['contraseña', 'en'] => ['password'],
                    ['contraseña', 'es'] => ['clave'],
                    default              => [],
                };
            }
        };
        $synonyms = new SearchKitManualSynonyms(new SynonymExpander([$provider], ['en', 'es']));

        self::assertSame(['cambiar clave', 'cambiar password'], $synonyms->alternatives('cambiar contraseña', 'es'));
        self::assertSame([], $synonyms->alternatives('', 'en'));
    }
}
