<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\NowoManualKitBundle;
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
     */
    public function __construct(private readonly array $manualConfig = [])
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
        return sys_get_temp_dir() . '/nowo-manual-kit-bundle/' . md5(serialize($this->manualConfig)) . '/cache';
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
        if (isset($this->manualConfig['machine_translation'])) {
            $container->services()->set('test.translation_responses', EchoTranslationResponses::class);
            $container->extension('framework', ['http_client' => ['mock_response_factory' => 'test.translation_responses']]);
        }
        $container->extension('twig', ['strict_variables' => true]);
        // The 404 / 401 the tests provoke stay out of the output.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_manual_kit', array_replace([
            'directory' => '%kernel.project_dir%/tests/Fixtures/manual',
            'docs_url'  => 'https://github.com/acme/app/blob/main/docs/',
        ], $this->manualConfig));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
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
