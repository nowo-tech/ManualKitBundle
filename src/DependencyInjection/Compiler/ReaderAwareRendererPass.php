<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Render\ReaderAwareManualRendererInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_a;
use function is_string;
use function sprintf;

/**
 * 1.13 (audit MK113-M1): with `nowo_manual_kit.roots` configured, the renderer behind {@see ManualRendererInterface}
 * must implement {@see ReaderAwareManualRendererInterface}. A 1.12-style renderer only knows `render($chapter, $admin)`
 * — the default root's audiences and routes — so it would render a portal page with staff blocks and links. The
 * container build fails instead; a renderer whose class cannot be known (a factory) fails too.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ReaderAwareRendererPass implements CompilerPassInterface
{
    /**
     * Checks the class of the renderer when roots are configured.
     */
    public function process(ContainerBuilder $container): void
    {
        $roots = $container->hasParameter('nowo_manual_kit.roots') ? $container->getParameter('nowo_manual_kit.roots') : [];
        if ($roots === [] || !$container->has(ManualRendererInterface::class)) {
            return;
        }
        $definition = $container->findDefinition(ManualRendererInterface::class);
        $class      = $container->getParameterBag()->resolveValue($definition->getClass());
        if (!is_string($class) || !is_a($class, ReaderAwareManualRendererInterface::class, true)) {
            throw new LogicException(sprintf('"nowo_manual_kit.roots" is configured, so the manual renderer (%s, aliased to %s) must implement %s: a renderer without renderFor() cannot render the chapters of another root for its readers.', is_string($class) ? $class : 'unknown class', ManualRendererInterface::class, ReaderAwareManualRendererInterface::class));
        }
    }
}
