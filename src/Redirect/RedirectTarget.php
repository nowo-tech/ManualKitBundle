<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

use InvalidArgumentException;

use function explode;
use function preg_match;
use function sprintf;
use function str_contains;

/**
 * 1.11: one end of a chapter redirect — a chapter slug and, optionally, a heading anchor (`new-slug#anchor`).
 *
 * Only internal slugs exist here: a slug is `[A-Za-z0-9-]+` and an anchor letters, digits, `_` and `-`, so no scheme,
 * host, `//` or path can ever be built from a target (no open redirect).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RedirectTarget
{
    /** `slug` or `slug#anchor`: what a map key and a map target look like. */
    public const string PATTERN = '/^[A-Za-z0-9-]+(?:#[\p{L}\p{N}_-]+)?\z/u';

    /**
     * @throws InvalidArgumentException when the slug or the anchor is not an internal one
     */
    public function __construct(
        public string $slug,
        public ?string $anchor = null,
    ) {
        if (preg_match(self::PATTERN, $anchor === null ? $slug : $slug . '#' . $anchor) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s%s" is not a chapter slug (letters, digits and dashes) with an optional #anchor.', $slug, $anchor === null ? '' : '#' . $anchor));
        }
    }

    /**
     * Parses `slug` or `slug#anchor`.
     *
     * @throws InvalidArgumentException when the value is not an internal slug
     */
    public static function fromString(string $value): self
    {
        if (!str_contains($value, '#')) {
            return new self($value);
        }
        [$slug, $anchor] = explode('#', $value, 2);

        return new self($slug, $anchor);
    }

    /**
     * Whether a value is a valid `slug` or `slug#anchor`.
     */
    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * `slug` or `slug#anchor`.
     */
    public function toString(): string
    {
        return $this->anchor === null ? $this->slug : $this->slug . '#' . $this->anchor;
    }
}
