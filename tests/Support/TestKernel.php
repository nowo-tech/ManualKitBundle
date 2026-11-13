<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\NowoManualKitBundle;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Kernel for integration and functional tests: the fixture manual (en + es), in-memory users (alice, admin) on HTTP
 * basic and the bundle routes under /{_locale}/manual. SearchKit stays unregistered: the provider only needs its
 * interface, which is installed.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $manualConfig merged over the test configuration
     * @param bool $hostControllersOnly route only the host's default `routing.controllers` import (Symfony >= 7.3
     *                                  skeleton routes.yaml), without importing the bundle routes.php
     * @param class-string|null $chapterAccessChecker 1.9: host ManualChapterAccessCheckerInterface implementation
     * @param class-string|null $accessPolicy 1.9: host ManualAccessInterface implementation
     * @param class-string|null $catalog 1.10: host ManualCatalogInterface implementation (it decorates the bundle catalog)
     * @param class-string|null $renderer 1.10: host ManualRendererInterface implementation
     */
    public function __construct(private readonly array $manualConfig = [], private readonly bool $hostControllersOnly = false, private readonly ?string $chapterAccessChecker = null, private readonly ?string $accessPolicy = null, private readonly ?string $catalog = null, private readonly ?string $renderer = null)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        // REQ-TWIG-004: Twig Extra registered like in a Flex application.
        yield new TwigExtraBundle();
        yield new NowoManualKitBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-manual-kit-bundle/' . md5(serialize([$this->manualConfig, $this->hostControllersOnly, $this->chapterAccessChecker, $this->accessPolicy, $this->catalog, $this->renderer])) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-manual-kit-bundle/logs';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'manual-kit-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'translator'            => ['enabled' => true, 'fallbacks' => ['en']],
            'default_locale'        => 'en',
            'enabled_locales'       => ['en', 'es'],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'cache'                 => ['app' => 'cache.adapter.array'],
            'router'                => ['utf8' => true],
        ]);
        $container->extension('security', [
            'providers' => ['test_users' => ['memory' => ['users' => [
                'alice' => ['password' => 'x', 'roles' => ['ROLE_USER']],
                'admin' => ['password' => 'x', 'roles' => ['ROLE_ADMIN', 'ROLE_USER']],
            ]]]],
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'firewalls'        => ['main' => ['lazy' => true, 'provider' => 'test_users', 'http_basic' => null]],
        ]);
        if ($this->chapterAccessChecker !== null) {
            // 1.9: a host per-chapter rule replacing the allow-all default.
            $container->services()->set('test.chapter_access', $this->chapterAccessChecker);
            $container->services()->alias(ManualChapterAccessCheckerInterface::class, 'test.chapter_access');
        }
        if ($this->accessPolicy !== null) {
            // 1.9: a host replacing the whole ManualAccessInterface.
            $container->services()->set('test.access_policy', $this->accessPolicy)->autowire();
            $container->services()->alias(ManualAccessInterface::class, 'test.access_policy');
        }
        if ($this->catalog !== null) {
            // 1.10: a host catalog replacing the ManualCatalogInterface port (it wraps the bundle catalog).
            $container->services()->set('test.host_catalog', $this->catalog)->autowire()->arg('$inner', service(ManualCatalog::class));
            $container->services()->alias(ManualCatalogInterface::class, 'test.host_catalog');
        }
        if ($this->renderer !== null) {
            // 1.10: a host renderer replacing the ManualRendererInterface port.
            $container->services()->set('test.host_renderer', $this->renderer);
            $container->services()->alias(ManualRendererInterface::class, 'test.host_renderer');
        }
        // 1.9: a host machine translator for `provider: service`.
        $container->services()->set('test.machine_translator', UppercaseMachineTranslator::class)->public();
        if (isset($this->manualConfig['machine_translation'])) {
            $container->services()->set('test.translation_responses', EchoTranslationResponses::class);
            $container->extension('framework', ['http_client' => ['mock_response_factory' => 'test.translation_responses']]);
        }
        // 1.8: host templates extending the bundle chapter page (tests/Fixtures/templates, namespace @ManualTest).
        $container->extension('twig', ['strict_variables' => true, 'paths' => ['%kernel.project_dir%/tests/Fixtures/templates' => 'ManualTest']]);
        // The 404 / 401 the tests provoke stay out of the output.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_manual_kit', array_replace([
            'directory' => '%kernel.project_dir%/tests/Fixtures/manual',
            'docs_url'  => 'https://github.com/acme/app/blob/main/docs/',
        ], $this->manualConfig));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        if ($this->hostControllersOnly) {
            $routes->import('routing.controllers');

            return;
        }
        $routes->import('@NowoManualKitBundle/Resources/config/routes.php')->prefix('/{_locale}/manual', false)->requirements(['_locale' => 'en|es']);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'Nowo\\ManualKitBundle\\') || str_starts_with($id, 'nowo_manual_kit.')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}
