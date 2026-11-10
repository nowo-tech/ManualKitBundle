<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Translation\MachineTranslatorInterface;

use function array_map;
use function count;
use function strtoupper;

/**
 * 1.9: a host translation port for `machine_translation.provider: service` (upper-cases every fragment).
 */
final class UppercaseMachineTranslator implements MachineTranslatorInterface
{
    /** @var list<array{string, string, int}> */
    public array $calls = [];

    public function translate(array $texts, string $sourceLocale, string $targetLocale): array
    {
        $this->calls[] = [$sourceLocale, $targetLocale, count($texts)];

        return array_map(static fn (string $text): string => strtoupper($text), $texts);
    }
}
