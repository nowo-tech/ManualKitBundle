<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Audience;

use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * 1.13: the default {@see ManualReaderResolverInterface}. The default root opens with
 * {@see ManualAccessInterface::canRead()} (`role`) and grants `member` to every reader and `admin` through
 * {@see ManualAccessInterface::isAdmin()}, as before 1.13; another root opens with its own `role`. Every other audience
 * of the root is granted through `isGranted(<its attribute>)`, so voters and the role hierarchy apply. Without an
 * authorization checker, only `member` and `admin` can be granted (fail closed). Nothing is memoised.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ManualReaderResolver implements ManualReaderResolverInterface
{
    /**
     * Receives the reader / admin policy, the audiences configuration and the authorization checker.
     */
    public function __construct(
        private ManualAccessInterface $access,
        private ManualAudiences $audiences = new ManualAudiences(),
        private ?AuthorizationCheckerInterface $authorization = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function reader(ManualRoot $root): ?ManualReader
    {
        if (!$this->opens($root)) {
            return null;
        }
        $granted = [];
        foreach ($root->audiences as $audience) {
            if ($this->holds($root, $audience)) {
                $granted[] = $audience;
            }
        }

        return new ManualReader($root->name, $granted);
    }

    private function opens(ManualRoot $root): bool
    {
        if ($root->isDefault()) {
            return $this->access->canRead();
        }

        return $root->role === '' || ($this->authorization instanceof AuthorizationCheckerInterface && $this->authorization->isGranted($root->role));
    }

    private function holds(ManualRoot $root, string $audience): bool
    {
        if ($audience === ManualAudiences::MEMBER) {
            return $root->isDefault();
        }
        if ($audience === ManualAudiences::ADMIN) {
            return $this->access->isAdmin();
        }
        $attribute = $this->audiences->attribute($audience);

        return $attribute !== null && $this->authorization instanceof AuthorizationCheckerInterface && $this->authorization->isGranted($attribute);
    }
}
