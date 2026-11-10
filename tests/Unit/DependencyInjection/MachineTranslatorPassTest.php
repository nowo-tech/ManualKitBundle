<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\ManualKitBundle\DependencyInjection\Compiler\MachineTranslatorPass;
use Nowo\ManualKitBundle\Tests\Support\UppercaseMachineTranslator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * 1.9 (audit KN-M-I2): `machine_translation.service` must name a MachineTranslatorInterface.
 */
final class MachineTranslatorPassTest extends TestCase
{
    public function testAServiceThatIsNoTranslatorFailsTheCompile(): void
    {
        $container = $this->container(new Definition(stdClass::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"nowo_manual_kit.machine_translation.service" points to "app.translator" (stdClass)');

        (new MachineTranslatorPass())->process($container);
    }

    public function testATranslatorPassesAndAClassParameterIsResolved(): void
    {
        $container = $this->container(new Definition('%app.translator_class%'));
        $container->setParameter('app.translator_class', UppercaseMachineTranslator::class);

        (new MachineTranslatorPass())->process($container);
        $this->addToAssertionCount(1);
    }

    public function testNothingToCheckWithoutAliasOrKnownClass(): void
    {
        (new MachineTranslatorPass())->process(new ContainerBuilder());
        (new MachineTranslatorPass())->process($this->container((new Definition())->setFactory('app_factory')));
        $this->addToAssertionCount(2);
    }

    private function container(Definition $definition): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.translator', $definition);
        $container->setAlias('nowo_manual_kit.machine_translator', 'app.translator');

        return $container;
    }
}
