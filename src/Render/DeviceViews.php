<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use InvalidArgumentException;

use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strrpos;
use function substr;

/**
 * 1.12: `nowo_manual_kit.device_views` — desktop / phone views of a chapter. Immutable configuration, shared by the
 * renderer, the controller and the search (no request state: FrankenPHP worker mode).
 *
 * - `<!-- device: phone -->…<!-- /device -->` and `<!-- device: desktop -->…<!-- /device -->` hold the content of one
 *   device; everything else is shared. Only the names in {@see self::DEVICES} are device blocks.
 * - `images/x.webp` has a phone companion `images/x{image_suffix}.webp` used in the phone view when the file exists.
 * - Disabled (the default), chapters render exactly as in 1.11.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceViews
{
    public const string DESKTOP = 'desktop';
    public const string PHONE   = 'phone';

    /** The allow-list of device names (blocks, query parameter, data attributes), in switch order. */
    public const array DEVICES = [self::DESKTOP, self::PHONE];

    /** Query parameter of a search hit in a device-specific section (`?device=phone`). */
    public const string QUERY = 'device';

    /** The Markdown comment kind (`<!-- device: phone -->`). */
    public const string KIND = 'device';

    /** Default viewport width (CSS px) from which the desktop view is the default. */
    public const int DEFAULT_BREAKPOINT = 768;

    /** Default companion suffix: `x.webp` → `x.phone.webp`. */
    public const string DEFAULT_IMAGE_SUFFIX = '.phone';

    /** A companion suffix: one separator then letters, digits, `_` or `-` (never a path, never `..`). */
    public const string IMAGE_SUFFIX_PATTERN = '/^[._-][A-Za-z0-9_-]{1,32}\z/';

    /**
     * @param bool $enabled device blocks, the switch and phone companions (false: the 1.11 rendering)
     * @param int $breakpoint viewport width (CSS px) below which the phone view is the default
     * @param string $imageSuffix inserted before the extension of an image to find its phone companion
     */
    public function __construct(
        public bool $enabled = false,
        public int $breakpoint = self::DEFAULT_BREAKPOINT,
        public string $imageSuffix = self::DEFAULT_IMAGE_SUFFIX,
    ) {
        if (preg_match(self::IMAGE_SUFFIX_PATTERN, $imageSuffix) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid device_views.image_suffix "%s": one of "._-" then up to 32 letters, digits, "_" or "-" (e.g. ".phone").', $imageSuffix));
        }
    }

    /**
     * Whether a value is one of the allowed device names.
     *
     * @phpstan-assert-if-true 'desktop'|'phone' $device
     */
    public static function isDevice(mixed $device): bool
    {
        return is_string($device) && in_array($device, self::DEVICES, true);
    }

    /**
     * The phone companion's file name (`x.webp` → `x.phone.webp`), or null for a file that is itself a companion or
     * has no extension. A name only: the caller resolves it inside the manual's images directory.
     */
    public function companion(string $file): ?string
    {
        $dot = strrpos($file, '.');
        if ($dot === false || $dot === 0) {
            return null;
        }
        $stem = substr($file, 0, $dot);
        if (str_ends_with($stem, $this->imageSuffix)) {
            return null;
        }

        return $stem . $this->imageSuffix . substr($file, $dot);
    }
}
