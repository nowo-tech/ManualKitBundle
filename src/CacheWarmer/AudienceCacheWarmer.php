<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\CacheWarmer;

use Nowo\ManualKitBundle\Audience\AudienceManifestValidator;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use UnexpectedValueException;

use function implode;

/**
 * 1.13: `cache:warmup` fails when the manifest or a chapter block names an audience that is not configured — the
 * check of the container build, again with the runtime `directory` (one that comes from an env var cannot be read
 * while the container is built). Not optional: a deploy never warms a manual that would hide or show the wrong
 * chapters.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class AudienceCacheWarmer implements CacheWarmerInterface
{
    /**
     * Receives the manual directory and the audiences configuration.
     */
    public function __construct(
        private string $directory,
        private ManualAudiences $audiences,
    ) {
    }

    /**
     * Never optional.
     */
    public function isOptional(): bool
    {
        return false;
    }

    /**
     * Validates the audiences of the manual; writes nothing.
     *
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $errors = AudienceManifestValidator::validate($this->directory, $this->audiences)['errors'];
        if ($errors !== []) {
            throw new UnexpectedValueException('Unknown manual audiences (configure them in nowo_manual_kit.audiences): ' . implode(' ', $errors));
        }

        return [];
    }
}
