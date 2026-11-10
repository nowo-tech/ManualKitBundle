<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\DependencyInjection\NowoManualKitExtension;
use Nowo\ManualKitBundle\Translation\MachineTranslatorInterface;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.9: `machine_translation.provider: service` wires the host's MachineTranslatorInterface; the interface is aliased
 * to the configured provider.
 */
final class MachineTranslatorWiringTest extends TestCase
{
    public function testServiceProviderUsesTheHostService(): void
    {
        $container = $this->load(['provider' => 'service', 'service' => 'app.translation_gateway']);

        self::assertSame('app.translation_gateway', (string) $container->getAlias('nowo_manual_kit.machine_translator'));
        self::assertSame('nowo_manual_kit.machine_translator', (string) $container->getAlias(MachineTranslatorInterface::class));
        $argument = $container->getDefinition(MarkdownTranslator::class)->getArgument('$translator');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('nowo_manual_kit.machine_translator', (string) $argument);
    }

    public function testBuiltInProviderIsAliasedToo(): void
    {
        $container = $this->load(['provider' => 'deepl', 'api_key' => 'k:fx']);

        self::assertTrue($container->hasDefinition('nowo_manual_kit.machine_translator'));
        self::assertSame('nowo_manual_kit.machine_translator', (string) $container->getAlias(MachineTranslatorInterface::class));
    }

    public function testNoProviderNoTranslator(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->has('nowo_manual_kit.machine_translator'));
        self::assertFalse($container->hasAlias(MachineTranslatorInterface::class));
        self::assertNull($container->getDefinition(MarkdownTranslator::class)->getArgument('$translator'));
    }

    /**
     * @param array<string, mixed> $machine
     */
    private function load(array $machine): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        $container->setParameter('kernel.enabled_locales', ['en']);
        new NowoManualKitExtension()->load([['directory' => '/app/manual', 'machine_translation' => $machine]], $container);

        return $container;
    }
}
