<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle;

use Nowo\ManualKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\ManualKitBundle\DependencyInjection\NowoManualKitExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * In-app product manual from `docs/manual` Markdown: translations per locale with stable anchors, audiences that fail
 * closed, figures with a lightbox and search tags; see docs/USAGE.md.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoManualKitBundle extends Bundle
{
    /**
     * Registers the Twig template paths compiler pass.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
    }

    /**
     * Returns the bundle extension (alias `nowo_manual_kit`).
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Symfony bundle extension lazy-init at boot; not request state
            $this->extension = new NowoManualKitExtension();
        }

        return $this->extension;
    }
}
