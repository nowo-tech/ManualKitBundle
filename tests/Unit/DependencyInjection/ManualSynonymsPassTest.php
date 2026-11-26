<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\DependencyInjection\Compiler\ManualSynonymsPass;
use Nowo\ManualKitBundle\Search\ManualSynonymsInterface;
use Nowo\ManualKitBundle\Search\SearchKitManualSynonyms;
use Nowo\ManualKitBundle\Tests\Support\HostManualSynonyms;
use Nowo\SearchKitBundle\Search\Synonym\SynonymExpander;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.15: SearchKit's synonyms back the manual search only when SearchKit is registered with its synonyms on and the host
 * defines no port of its own.
 */
final class ManualSynonymsPassTest extends TestCase
{
    public function testSearchKitSynonymsAreWiredWhenOn(): void
    {
        $container = $this->container(true, true);

        (new ManualSynonymsPass())->process($container);

        self::assertSame(SearchKitManualSynonyms::class, (string) $container->getAlias(ManualSynonymsInterface::class));
        $argument = $container->getDefinition(SearchKitManualSynonyms::class)->getArgument('$expander');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(SynonymExpander::class, (string) $argument);
    }

    /**
     * @return iterable<string, array{bool, mixed, bool}>
     */
    public static function notWired(): iterable
    {
        yield 'SearchKit not registered' => [false, null, false];
        yield 'no synonyms parameter' => [true, null, false];
        yield 'synonyms off' => [true, false, false];
        yield 'not a boolean' => [true, 'true', false];
        yield 'the host has its own port' => [true, true, true];
    }

    #[DataProvider('notWired')]
    public function testNothingIsWiredOtherwise(bool $expander, mixed $enabled, bool $hostPort): void
    {
        $container = $this->container($expander, $enabled);
        if ($hostPort) {
            $container->register(HostManualSynonyms::class, HostManualSynonyms::class);
            $container->setAlias(ManualSynonymsInterface::class, HostManualSynonyms::class);
        }

        (new ManualSynonymsPass())->process($container);

        self::assertFalse($container->hasDefinition(SearchKitManualSynonyms::class));
        self::assertSame($hostPort, $container->has(ManualSynonymsInterface::class));
    }

    private function container(bool $expander, mixed $enabled): ContainerBuilder
    {
        $container = new ContainerBuilder();
        if ($expander) {
            $container->register(SynonymExpander::class, SynonymExpander::class);
        }
        if ($enabled !== null) {
            $container->setParameter(ManualSynonymsPass::ENABLED_PARAMETER, $enabled);
        }

        return $container;
    }
}
