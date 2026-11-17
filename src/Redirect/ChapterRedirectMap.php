<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

use InvalidArgumentException;

use function array_map;
use function implode;

/**
 * 1.11: the default {@see ChapterRedirectsInterface} — immutable lookups compiled once, when the container is built
 * (NowoManualKitExtension), from `nowo_manual_kit.redirects` and the manifest's `redirects:` file. No state is ever
 * written after construction, so one instance serves every request of a FrankenPHP worker.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ChapterRedirectMap implements ChapterRedirectsInterface
{
    /**
     * Receives the compiled lookups ({@see CompiledRedirectMap}); use {@see self::fromRules()} to build one from a map.
     *
     * @param array<string, string> $rules
     * @param array<string, string> $chapters
     * @param array<string, array<string, array<string, string>>> $arrivals
     * @param array<string, array<string, string>> $own
     */
    public function __construct(
        private array $rules = [],
        private array $chapters = [],
        private array $arrivals = [],
        private array $own = [],
    ) {
    }

    /**
     * Compiles a map (old → new).
     *
     * @param array<mixed> $rules
     *
     * @throws InvalidArgumentException listing every invalid entry and cycle
     */
    public static function fromRules(array $rules): self
    {
        $compiled = RedirectMapCompiler::compile($rules);
        if ($compiled->errors !== []) {
            throw new InvalidArgumentException('Invalid manual redirect map: ' . implode(' ', $compiled->errors));
        }

        return new self($compiled->rules, $compiled->chapters, $compiled->arrivals, $compiled->own);
    }

    /**
     * {@inheritDoc}
     */
    public function chapterTarget(string $slug): ?RedirectTarget
    {
        return isset($this->chapters[$slug]) ? RedirectTarget::fromString($this->chapters[$slug]) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasMovedAnchors(string $slug): bool
    {
        $final = $this->chapterTarget($slug);

        return $final instanceof RedirectTarget && isset($this->arrivals[$final->slug][$slug]);
    }

    /**
     * {@inheritDoc}
     */
    public function movedAnchors(string $page, string $from): array
    {
        return array_map(RedirectTarget::fromString(...), $this->arrivals[$page][$from] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function ownAnchors(string $page): array
    {
        return array_map(RedirectTarget::fromString(...), $this->own[$page] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
