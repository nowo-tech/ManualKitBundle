<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

use function in_array;
use function is_string;

/**
 * 1.13: which manual root a request is for. A manual route carries its root (`_nowo_manual_root`, set by the
 * `nowo_manual` route loader; absent = the default root). Any other request (the SearchKit endpoint, a page printing
 * `manual_href()`) is in the root that lists its firewall in `roots.*.firewalls`, else in the default root.
 *
 * A root is only served inside its own firewalls: a root that lists firewalls is refused anywhere else, and a root is
 * refused in a firewall another root lists (null), so a route import under the wrong firewall serves nothing. Stateless:
 * everything is read from the request on each call.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualRootResolver
{
    /**
     * Receives the audiences configuration and (when SecurityBundle is installed) the security helper.
     */
    public function __construct(
        private ManualAudiences $audiences = new ManualAudiences(),
        private ?Security $security = null,
    ) {
    }

    /**
     * The root of a request: its route's root, else its firewall's, else the default one; null when that root is not
     * configured or not served in the request's firewall.
     */
    public function resolve(?Request $request): ?ManualRoot
    {
        if (!$request instanceof Request) {
            return $this->audiences->defaultRoot();
        }
        $name = $request->attributes->get(ManualRoot::ATTRIBUTE);
        if ($name === null) {
            $firewall = $this->firewall($request);
            $name     = $firewall === null ? ManualRoot::DEFAULT : ($this->audiences->firewalls()[$firewall] ?? ManualRoot::DEFAULT);
        }
        $root = is_string($name) ? $this->audiences->root($name) : null;

        return $root instanceof ManualRoot && $this->servedIn($root, $request) ? $root : null;
    }

    /**
     * The root of a manual route: the route's `_nowo_manual_root` (absent: the default root, the routes of
     * `routes.php`); null when that root is not configured or not served in the request's firewall.
     */
    public function forRoute(Request $request): ?ManualRoot
    {
        $name = $request->attributes->get(ManualRoot::ATTRIBUTE, ManualRoot::DEFAULT);
        $root = is_string($name) ? $this->audiences->root($name) : null;

        return $root instanceof ManualRoot && $this->servedIn($root, $request) ? $root : null;
    }

    /**
     * Whether the root may be served in the request's firewall.
     */
    private function servedIn(ManualRoot $root, Request $request): bool
    {
        $firewall = $this->firewall($request);
        if ($root->firewalls !== []) {
            return $firewall !== null && in_array($firewall, $root->firewalls, true);
        }
        $claimedBy = $firewall === null ? null : ($this->audiences->firewalls()[$firewall] ?? null);

        return $claimedBy === null || $claimedBy === $root->name;
    }

    private function firewall(Request $request): ?string
    {
        return $this->security?->getFirewallConfig($request)?->getName();
    }
}
