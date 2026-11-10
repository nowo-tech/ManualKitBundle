<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\ManualKitBundle\Translation\MachineTranslatorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function class_exists;
use function is_a;
use function is_string;
use function sprintf;

/**
 * 1.9: with `machine_translation.provider: service`, the configured service must implement
 * {@see MachineTranslatorInterface}; the container compile fails otherwise (instead of a TypeError when
 * `nowo:manual:translate` runs).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MachineTranslatorPass implements CompilerPassInterface
{
    /**
     * Checks the class of the service behind `nowo_manual_kit.machine_translator` when it is known.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias('nowo_manual_kit.machine_translator')) {
            return;
        }

        $id    = (string) $container->getAlias('nowo_manual_kit.machine_translator');
        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass());
        // A factory or an unknown class is left to the runtime type check.
        if (!is_string($class) || !class_exists($class)) {
            return;
        }
        if (!is_a($class, MachineTranslatorInterface::class, true)) {
            throw new LogicException(sprintf('"nowo_manual_kit.machine_translation.service" points to "%s" (%s), which does not implement %s.', $id, $class, MachineTranslatorInterface::class));
        }
    }
}
