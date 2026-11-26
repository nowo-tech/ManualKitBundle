<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Search\ManualSynonymsInterface;

/**
 * 1.15: a host's own synonym port for the manual search (no SearchKit): whole-text alternatives per locale.
 */
final class HostManualSynonyms implements ManualSynonymsInterface
{
    private const array ALTERNATIVES = [
        'en' => ['find' => ['search'], 'people' => ['users'], 'settings' => ['administration'], 'begin' => ['start']],
        'es' => ['comienzo' => ['inicio']],
    ];

    public function alternatives(string $text, ?string $locale): array
    {
        return self::ALTERNATIVES[$locale ?? 'en'][$text] ?? [];
    }
}
