<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

/**
 * 1.11: the result of {@see RedirectMapCompiler::compile()} — the valid rules, the lookups built from them and the
 * problems found. Positions are `slug` or `slug#anchor` strings.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CompiledRedirectMap
{
    /**
     * @param array<string, string> $rules the valid rules as written
     * @param array<string, string> $chapters old chapter slug → final position
     * @param array<string, array<string, array<string, string>>> $arrivals landing chapter → old chapter → anchor
     *                                                                      (`''` = none) → final position
     * @param array<string, array<string, string>> $own existing chapter → moved anchor → final position
     * @param array<string, string> $chains rules whose target is redirected again → final position
     * @param list<string> $errors invalid entries and cycles
     */
    public function __construct(
        public array $rules,
        public array $chapters,
        public array $arrivals,
        public array $own,
        public array $chains,
        public array $errors,
    ) {
    }
}
