<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use Nowo\ManualKitBundle\Model\ManualChapter;

use function array_diff;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function preg_match;
use function serialize;
use function sort;
use function sprintf;
use function strtolower;
use function trim;

/**
 * 1.13: the named audiences of the manual (`nowo_manual_kit.audiences`), the default audience of a chapter that names
 * none, and the manual roots (`nowo_manual_kit.roots`). Immutable configuration only — no request or user data — so
 * one instance serves every request of a FrankenPHP worker.
 *
 * Two names are built in: `member` (every reader of the default root: the 1.12 default) and `admin` (`admin_role`, or
 * `audiences.admin`). `github` is reserved for blocks GitHub readers see and the app never shows. Deny by default: a
 * name that is not configured is shown to nobody (and fails the container build, see docs/CONFIGURATION.md).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualAudiences
{
    /** Every reader of the default root (no check beyond `nowo_manual_kit.role`). */
    public const string MEMBER = 'member';

    /** The administrators (`admin_role` / `audiences.admin`). */
    public const string ADMIN = 'admin';

    /** Blocks for repository readers only: never rendered in the app. */
    public const string GITHUB = 'github';

    /** An audience or root name. */
    public const string NAME = '/^[a-z][a-z0-9_]{0,31}\z/';

    /**
     * @param array<string, string> $attributes audience => role or security attribute (`admin` included)
     * @param string $defaultAudience the audience of a chapter whose manifest row names none
     * @param array<string, array{audiences: list<string>, role: string, home: string, firewalls: list<string>}> $roots
     *                                                                                                                  the roots other than the default one
     * @param string $defaultRole `nowo_manual_kit.role`: who opens the default root
     * @param list<string> $defaultFirewalls `nowo_manual_kit.default_firewalls`: the firewalls of the default root
     *                                       ([] = any firewall no other root lists)
     */
    public function __construct(
        private array $attributes = [self::ADMIN => 'ROLE_ADMIN'],
        private string $defaultAudience = self::MEMBER,
        private array $roots = [],
        private string $defaultRole = 'ROLE_USER',
        private array $defaultFirewalls = [],
    ) {
    }

    /**
     * Every audience a manifest row or a block may name (`member`, `admin` and the configured ones), sorted.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_values(array_unique([self::MEMBER, self::ADMIN, ...array_keys($this->attributes)]));
        sort($names);

        return $names;
    }

    /**
     * Whether a manifest row or block may name this audience.
     */
    public function isKnown(string $audience): bool
    {
        return in_array($audience, $this->names(), true);
    }

    /**
     * The audience of a chapter whose manifest row names none.
     */
    public function defaultAudience(): string
    {
        return $this->defaultAudience;
    }

    /**
     * The role or security attribute of a named audience (`admin` included), null for `member` and unknown names.
     */
    public function attribute(string $audience): ?string
    {
        return $this->attributes[$audience] ?? null;
    }

    /**
     * The root of this name, or null when none is configured.
     */
    public function root(string $name): ?ManualRoot
    {
        if ($name === ManualRoot::DEFAULT) {
            return $this->defaultRoot();
        }
        if (!isset($this->roots[$name])) {
            return null;
        }
        $root = $this->roots[$name];

        return new ManualRoot($name, $root['audiences'], $root['role'], $root['home'], $root['firewalls']);
    }

    /**
     * The default root: `member` plus every audience no other root lists.
     */
    public function defaultRoot(): ManualRoot
    {
        $claimed = [];
        foreach ($this->roots as $root) {
            $claimed = array_merge($claimed, $root['audiences']);
        }

        return new ManualRoot(ManualRoot::DEFAULT, array_values(array_diff($this->names(), $claimed)), $this->defaultRole, ManualChapter::HOME, $this->defaultFirewalls);
    }

    /**
     * The default root first, then the configured ones in configuration order.
     *
     * @return list<ManualRoot>
     */
    public function roots(): array
    {
        $roots = [$this->defaultRoot()];
        foreach (array_keys($this->roots) as $name) {
            $roots[] = $this->root($name) ?? $this->defaultRoot();
        }

        return $roots;
    }

    /**
     * Whether the reader may see the chapter by audience: every level of it (its parents, then itself) names an
     * audience the reader holds. Audiences of another root are never held, so this also keeps chapters in their root.
     */
    public function canSee(ManualChapter $chapter, ManualReader $reader): bool
    {
        foreach ($chapter->audienceLevels() as $level) {
            if (!$reader->holdsAny($level)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 1.13: whether the reader holds every configured audience (`member`, `admin` and the named ones) — the only
     * reader who keeps the text after a stray block marker and gets every image. Never true in a root that serves only
     * some audiences, whose chapters may hold blocks of other roots (MK113-H1).
     */
    public function isWhole(ManualReader $reader): bool
    {
        return array_diff($this->names(), $reader->audiences) === [];
    }

    /**
     * Whether a chapter belongs to a root (every level names an audience of the root), whoever reads it.
     */
    public function belongsTo(ManualChapter $chapter, ManualRoot $root): bool
    {
        return $this->canSee($chapter, new ManualReader($root->name, $root->audiences));
    }

    /**
     * What the configuration gets wrong, one sentence each ([] when valid): reserved or malformed names, a default
     * audience outside the default root, a root naming an unknown audience or `member`, a firewall claimed twice.
     *
     * @return list<string>
     */
    public function configErrors(): array
    {
        $errors = [];
        foreach (array_keys($this->attributes) as $name) {
            if (preg_match(self::NAME, $name) !== 1 || in_array($name, [self::MEMBER, self::GITHUB], true)) {
                $errors[] = sprintf('"nowo_manual_kit.audiences" cannot define %s: a name is 1 to 32 lower-case letters, digits or "_" starting with a letter, and "member" / "github" are reserved.', json_encode($name));
            }
        }
        if (!$this->isKnown($this->defaultAudience) || !$this->defaultRoot()->serves($this->defaultAudience)) {
            $errors[] = sprintf('"nowo_manual_kit.default_audience" %s must be "member" or an audience of "nowo_manual_kit.audiences" that no root of "nowo_manual_kit.roots" lists.', json_encode($this->defaultAudience));
        }
        $firewalls = [];
        foreach ($this->defaultFirewalls as $firewall) {
            $firewalls[$firewall] = ManualRoot::DEFAULT;
        }
        foreach ($this->roots as $name => $root) {
            if (preg_match(self::NAME, $name) !== 1 || $name === ManualRoot::DEFAULT) {
                $errors[] = sprintf('"nowo_manual_kit.roots" cannot define %s: a root name is 1 to 32 lower-case letters, digits or "_" starting with a letter, and "default" is the root of "role".', json_encode($name));
            }
            foreach ($root['audiences'] as $audience) {
                if ($audience === self::MEMBER || !$this->isKnown($audience)) {
                    $errors[] = sprintf('"nowo_manual_kit.roots.%s.audiences" lists %s: use audiences of "nowo_manual_kit.audiences" (or "admin"); "member" belongs to the default root.', $name, json_encode($audience));
                }
            }
            foreach ($root['firewalls'] as $firewall) {
                if (isset($firewalls[$firewall])) {
                    $errors[] = sprintf('The firewall "%s" is listed by the roots "%s" and "%s"; a firewall serves one root.', $firewall, $firewalls[$firewall], $name);
                }
                $firewalls[$firewall] = $name;
            }
        }

        return $errors;
    }

    /**
     * 1.13 (audit MK113-I2): a hash of the whole audiences configuration — part of the rendering cache key, so a
     * deploy that only changes audiences, the default audience or the roots never serves renderings made under the
     * previous configuration.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', serialize([$this->attributes, $this->defaultAudience, $this->roots, $this->defaultRole, $this->defaultFirewalls]));
    }

    /**
     * The firewall => root map of `default_firewalls` and `roots.*.firewalls`.
     *
     * @return array<string, string>
     */
    public function firewalls(): array
    {
        $map = [];
        foreach ($this->defaultFirewalls as $firewall) {
            $map[$firewall] = ManualRoot::DEFAULT;
        }
        foreach ($this->roots as $name => $root) {
            foreach ($root['firewalls'] as $firewall) {
                $map[$firewall] ??= $name;
            }
        }

        return $map;
    }

    /**
     * The audiences of a manifest `audience` value — `admin`, `admin, developer` or a YAML list — trimmed; null when
     * the value is not a non-empty string or list of strings.
     *
     * @return list<string>|null
     */
    public static function parse(mixed $value): ?array
    {
        $names = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : null);
        if ($names === null || $names === []) {
            return null;
        }
        $parsed = [];
        foreach ($names as $name) {
            if (!is_string($name) || trim($name) === '') {
                return null;
            }
            $parsed[] = trim($name);
        }

        return array_values(array_unique($parsed));
    }

    /**
     * The audiences of a block marker value (`admin`, `Admin, developer`): lower-cased, trimmed.
     *
     * @return list<string>
     */
    public static function parseBlock(string $value): array
    {
        return array_values(array_unique(array_map(static fn (string $name): string => strtolower(trim($name)), explode(',', $value))));
    }

    /**
     * A list of names for messages: `"a", "b"`.
     *
     * @param list<string> $names
     */
    public static function quote(array $names): string
    {
        return implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', $names));
    }
}
