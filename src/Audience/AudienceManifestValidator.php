<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use FilesystemIterator;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_key_exists;
use function array_unique;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_encode;
use function preg_match_all;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_UNESCAPED_SLASHES;

/**
 * 1.13: deny by default, loudly. Every audience the manifest (`audience:` of a row) or a chapter block
 * (`<!-- audience: a, b -->`, in any Markdown file of the manual, translations included, and in the chapters the
 * manifest takes from outside it) names must be configured; anything else is an error, raised when the container is
 * built (or, for a `directory` that comes from an env var, at `cache:warmup`). A malformed marker is not reported here:
 * it fails closed when the chapter is rendered.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AudienceManifestValidator
{
    /** The opening marker of an audience block, with one or several names (the value is group 1). */
    public const string BLOCK_OPENING = '/<!--\s*audience:\s*([a-z][a-z0-9_]*(?:\s*,\s*[a-z][a-z0-9_]*)*)\s*-->/i';

    /**
     * The errors of the manual in `$directory` ([] when every audience is known) and the files read.
     *
     * @return array{errors: list<string>, files: list<string>}
     */
    public static function validate(string $directory, ManualAudiences $audiences): array
    {
        $manifest = $directory . '/' . ManualCatalog::MANIFEST;
        if (!is_file($manifest)) {
            return ['errors' => [], 'files' => []];
        }
        $errors = [];
        try {
            $parsed = Yaml::parseFile($manifest);
        } catch (ParseException) {
            // The catalog reports it (nowo:manual:check); nothing to validate.
            $parsed = null;
        }
        $rows = is_array($parsed) && is_array($parsed['chapters'] ?? null) ? $parsed['chapters'] : [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !array_key_exists('audience', $row)) {
                continue;
            }
            $names   = ManualAudiences::parse($row['audience']);
            $label   = json_encode($row['slug'] ?? $row['include'] ?? '#' . $index, JSON_UNESCAPED_SLASHES);
            $unknown = $names === null ? null : self::unknown($names, $audiences, false);
            if ($unknown === null) {
                $errors[] = sprintf('%s: the audience of %s is not a name, "a, b" or a list of names.', ManualCatalog::MANIFEST, $label);
            } elseif ($unknown !== []) {
                $errors[] = sprintf('%s: %s names the unknown audience %s.', ManualCatalog::MANIFEST, $label, ManualAudiences::quote($unknown));
            }
        }

        $files = self::markdownFiles($directory, $audiences);
        foreach ($files as $file) {
            preg_match_all(self::BLOCK_OPENING, (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $value) {
                $unknown = self::unknown(ManualAudiences::parseBlock($value), $audiences, true);
                if ($unknown !== []) {
                    $errors[] = sprintf('%s: a block names the unknown audience %s.', self::relative($file, $directory), ManualAudiences::quote($unknown));
                }
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'files' => [$manifest, ...$files]];
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function unknown(array $names, ManualAudiences $audiences, bool $block): array
    {
        return array_values(array_filter($names, static fn (string $name): bool => !$audiences->isKnown($name) && (!$block || $name !== ManualAudiences::GITHUB)));
    }

    /**
     * Every Markdown file under the manual directory, then the chapters the manifest takes from outside it.
     *
     * @return list<string>
     */
    private static function markdownFiles(string $directory, ManualAudiences $audiences): array
    {
        $files = [];
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.md')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        foreach (new ManualCatalog($directory, audiences: $audiences)->all() as $chapter) {
            if ($chapter->external) {
                $files[] = $chapter->path;
            }
        }

        return array_values(array_unique($files));
    }

    private static function relative(string $file, string $directory): string
    {
        return str_starts_with($file, $directory . '/') ? substr($file, strlen($directory) + 1) : $file;
    }
}
