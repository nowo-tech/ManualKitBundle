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
 * 1.13: the named-audiences manual (tests/Fixtures/audiences) behind two real firewalls. `main` (HTTP basic, staff
 * users) serves the default root under /{_locale}/manual; `portal` (^/portal, its own user provider of CRM contacts)
 * serves the `portal` root under /portal/help. Two misplaced imports check that a root is never served in another
 * root's firewall: the default routes under /portal/misplaced and the portal routes under /{_locale}/misplaced-portal.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AudiencesTestKernel extends Kernel
{
    use MicroKernelTrait;

    /** Staff users of the `main` firewall: name => roles. */
    public const array STAFF = [
        'uma'     => ['ROLE_USER'],
        'ada'     => ['ROLE_USER', 'ROLE_ORG_ADMIN'],
        'dev'     => ['ROLE_USER', 'ROLE_DEVELOPER'],
        'both'    => ['ROLE_USER', 'ROLE_ORG_ADMIN', 'ROLE_DEVELOPER'],
        'insider' => ['ROLE_USER', 'ROLE_CRM_CONTACT'],
    ];

    /** CRM contacts of the `portal` firewall (not application users): name => roles. */
    public const array CONTACTS = [
        'carla'  => ['ROLE_CRM_CONTACT'],
        'noperm' => ['ROLE_PORTAL_GUEST'],
        'uma'    => ['ROLE_USER'],
    ];

    /**
     * @param array<string, mixed> $manualConfig merged over the audiences configuration
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
        yield new TwigExtraBundle();
        yield new NowoManualKitBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-manual-kit-bundle/audiences-' . md5(serialize($this->manualConfig)) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-manual-kit-bundle/logs';
    }

    /**
     * The audiences configuration of these tests.
     *
     * @return array<string, mixed>
     */
    public static function manualConfig(): array
    {
        return [
            'directory'        => '%kernel.project_dir%/tests/Fixtures/audiences/manual',
            'docs_url'         => 'https://github.com/acme/app/blob/main/docs/',
            'audiences'        => ['user' => 'ROLE_USER', 'admin' => 'ROLE_ORG_ADMIN', 'developer' => 'ROLE_DEVELOPER', 'customer' => 'ROLE_CRM_CONTACT'],
            'default_audience' => 'user',
            'roots'            => ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'home' => 'portal-home', 'firewalls' => ['portal']]],
        ];
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
        $users = static fn (array $list): array => array_map(static fn (array $roles): array => ['password' => 'x', 'roles' => $roles], $list);
        $container->extension('security', [
            'providers' => [
                'staff'    => ['memory' => ['users' => $users(self::STAFF)]],
                'contacts' => ['memory' => ['users' => $users(self::CONTACTS)]],
            ],
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'firewalls'        => [
                'portal' => ['pattern' => '^/portal', 'lazy' => true, 'provider' => 'contacts', 'http_basic' => null],
                'main'   => ['lazy' => true, 'provider' => 'staff', 'http_basic' => null],
            ],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_manual_kit', array_replace(self::manualConfig(), $this->manualConfig));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoManualKitBundle/Resources/config/routes.php')->prefix('/{_locale}/manual', false)->requirements(['_locale' => 'en|es']);
        $routes->import('portal', 'nowo_manual')->prefix('/portal/help', false);
        // Misplaced on purpose: each root imported inside the other root's firewall.
        $routes->import('@NowoManualKitBundle/Resources/config/routes.php')->prefix('/portal/misplaced', false)->namePrefix('misplaced_');
        $routes->import('portal', 'nowo_manual')->prefix('/{_locale}/misplaced-portal', false)->namePrefix('misplaced_')->requirements(['_locale' => 'en|es']);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'Nowo\\ManualKitBundle\\')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}
