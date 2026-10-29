<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

use function is_scalar;

/**
 * Logger that keeps every record as ["<level> <message>", context] for assertions.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{0: string, 1: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [(is_scalar($level) ? (string) $level : '') . ' ' . $message, $context];
    }
}
