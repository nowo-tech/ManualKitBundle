<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Redirect;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_key_first;
use function count;
use function get_debug_type;
use function is_array;
use function is_file;
use function is_string;
use function preg_match;
use function realpath;
use function sprintf;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * 1.11: gathers the redirect map from `nowo_manual_kit.redirects` (inline) and the manifest's `redirects:` entry — a
 * YAML file of the manual directory (`redirects: redirects.yaml`) or an inline mapping. Read once, when the container
 * is built; the files it read are returned so the container is rebuilt when they change (debug).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RedirectMapLoader
{
    /**
     * @param array<mixed> $inline `nowo_manual_kit.redirects`
     *
     * @return array{rules: array<mixed>, files: list<string>, errors: list<string>} the merged map (not validated
     *                                                                               yet), the files read and the problems found
     */
    public static function load(array $inline, string $directory): array
    {
        $manifestPath = $directory . '/' . ManualCatalog::MANIFEST;
        if (!is_file($manifestPath)) {
            return ['rules' => $inline, 'files' => [], 'errors' => []];
        }
        try {
            $manifest = Yaml::parseFile($manifestPath);
        } catch (ParseException) {
            // The catalog reports an unreadable manifest (nowo:manual:check); its redirects cannot be read either.
            return ['rules' => $inline, 'files' => [$manifestPath], 'errors' => []];
        }
        $entry = is_array($manifest) ? ($manifest['redirects'] ?? null) : null;
        if ($entry === null) {
            return ['rules' => $inline, 'files' => [$manifestPath], 'errors' => []];
        }

        $files = [$manifestPath];
        if (is_string($entry)) {
            [$entry, $file, $error] = self::file($entry, $directory);
            if ($file !== null) {
                $files[] = $file;
            }
            if ($error !== null) {
                return ['rules' => $inline, 'files' => $files, 'errors' => [$error]];
            }
        }
        if (!is_array($entry)) {
            return ['rules' => $inline, 'files' => $files, 'errors' => [sprintf('%s: "redirects" must be the name of a YAML file of the manual directory or a mapping of old → new paths, got %s.', ManualCatalog::MANIFEST, get_debug_type($entry))]];
        }

        $errors = [];
        $rules  = $inline;
        foreach ($entry as $from => $to) {
            if (isset($inline[$from]) && $inline[$from] !== $to) {
                $errors[] = sprintf('Redirect "%s" is mapped twice: "%s" in nowo_manual_kit.redirects and "%s" in the manifest redirects.', (string) $from, is_string($inline[$from]) ? $inline[$from] : get_debug_type($inline[$from]), is_string($to) ? $to : get_debug_type($to));

                continue;
            }
            $rules[$from] = $to;
        }

        return ['rules' => $rules, 'files' => $files, 'errors' => $errors];
    }

    /**
     * Reads a redirects file named by the manifest: a `.yaml` / `.yml` file inside the manual directory holding the
     * map, at the top level or under `redirects:`.
     *
     * @return array{0: mixed, 1: string|null, 2: string|null} the map, the file read and the problem
     */
    private static function file(string $name, string $directory): array
    {
        $root = realpath($directory);
        $path = realpath($directory . '/' . $name);
        if (preg_match('/\.ya?ml\z/', $name) !== 1 || $root === false || $path === false || !is_file($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return [null, null, sprintf('%s: the redirects file "%s" must be an existing .yaml file inside %s.', ManualCatalog::MANIFEST, $name, $directory)];
        }
        try {
            $content = Yaml::parseFile($path);
        } catch (ParseException $e) {
            return [null, $path, sprintf('The redirects file %s cannot be parsed: %s', $name, $e->getMessage())];
        }
        if (is_array($content) && count($content) === 1 && array_key_first($content) === 'redirects') {
            $content = $content['redirects'];
        }

        return [$content ?? [], $path, null];
    }
}
