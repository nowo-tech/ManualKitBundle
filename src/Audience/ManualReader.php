<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use function array_intersect;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function sort;

/**
 * 1.13: who reads a page — the manual root and the audiences of that root the current user holds. Built per request
 * (never kept in a service: FrankenPHP worker mode), and used as the rendering cache key: two readers with the same
 * root and audiences get the same rendering.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualReader
{
    /** @var list<string> sorted, without duplicates */
    public array $audiences;

    /**
     * @param string $root the name of the root ({@see ManualRoot::DEFAULT} or a key of `nowo_manual_kit.roots`)
     * @param list<string> $audiences the audiences of that root this reader holds
     */
    public function __construct(
        public string $root,
        array $audiences,
    ) {
        $audiences = array_values(array_unique($audiences));
        sort($audiences);
        $this->audiences = $audiences;
    }

    /**
     * The reader of the 1.12 API (`render($chapter, $admin)`): the default root, `member`, plus `admin` for
     * administrators.
     */
    public static function legacy(bool $admin): self
    {
        return new self(ManualRoot::DEFAULT, $admin ? [ManualAudiences::MEMBER, ManualAudiences::ADMIN] : [ManualAudiences::MEMBER]);
    }

    /**
     * Whether the reader holds this audience.
     */
    public function has(string $audience): bool
    {
        return in_array($audience, $this->audiences, true);
    }

    /**
     * Whether the reader holds at least one of these audiences (a chapter level, a block).
     *
     * @param list<string> $audiences
     */
    public function holdsAny(array $audiences): bool
    {
        return array_intersect($audiences, $this->audiences) !== [];
    }

    /**
     * A stable key of the root and the audiences (rendering cache key).
     */
    public function key(): string
    {
        return $this->root . ':' . implode(',', $this->audiences);
    }
}
