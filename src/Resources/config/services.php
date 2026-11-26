<?php

declare(strict_types=1);

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReaderResolver;
use Nowo\ManualKitBundle\Audience\ManualReaderResolverInterface;
use Nowo\ManualKitBundle\Audience\ManualRootResolver;
use Nowo\ManualKitBundle\CacheWarmer\AudienceCacheWarmer;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Command\CheckTranslationsCommand;
use Nowo\ManualKitBundle\Command\RedirectsCommand;
use Nowo\ManualKitBundle\Command\StampTranslationCommand;
use Nowo\ManualKitBundle\Command\TranslateManualCommand;
use Nowo\ManualKitBundle\Controller\ManualController;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectMap;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectsInterface;
use Nowo\ManualKitBundle\Render\DeviceViews;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;
use Nowo\ManualKitBundle\Routing\ManualRouteLoader;
use Nowo\ManualKitBundle\Search\ManualSearch;
use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Search\ManualSynonymsInterface;
use Nowo\ManualKitBundle\Security\AllowAllManualChapterAccessChecker;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use Nowo\ManualKitBundle\Translation\UiGlossary;
use Nowo\ManualKitBundle\Twig\ManualLinkExtension;
use Nowo\SearchKitBundle\Contract\SearchProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Service definitions for NowoManualKitBundle. The SearchKit provider is registered only when
 * nowo-tech/search-kit-bundle is installed.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private();

    // 1.13: the named audiences, the default audience and the roots (arguments set by NowoManualKitExtension).
    $services->set(ManualAudiences::class);

    $services->set(ManualCatalog::class)
        ->args([param('nowo_manual_kit.directory'), param('nowo_manual_kit.source_locale'), param('nowo_manual_kit.locales'), service(ManualAudiences::class)]);
    // 1.10: the catalog as a port (REQ-ARCH-001 rule 1); alias it to your own service to serve chapters from elsewhere.
    $services->alias(ManualCatalogInterface::class, ManualCatalog::class);

    // 1.12: `device_views` (arguments set by NowoManualKitExtension).
    $services->set(DeviceViews::class);

    // `$cache` is the configured pool, set by NowoManualKitExtension.
    $services->set(ManualRenderer::class)
        ->arg('$docsUrl', param('nowo_manual_kit.docs_url'))
        ->arg('$devices', service(DeviceViews::class));
    // 1.10: the renderer as a port; alias it to your own service to replace it.
    $services->alias(ManualRendererInterface::class, ManualRenderer::class);

    // 1.9: per-chapter rule of the host (alias the interface to your own service); allow-all by default.
    $services->set(AllowAllManualChapterAccessChecker::class);
    $services->alias(ManualChapterAccessCheckerInterface::class, AllowAllManualChapterAccessChecker::class);

    // 1.11: the redirect map, compiled by NowoManualKitExtension (its arguments); alias the port to replace it.
    $services->set(ChapterRedirectMap::class);
    $services->alias(ChapterRedirectsInterface::class, ChapterRedirectMap::class);

    $services->set(ManualAccess::class)
        ->arg('$role', param('nowo_manual_kit.role'))
        ->arg('$adminRole', param('nowo_manual_kit.admin_role'))
        ->arg('$chapters', service(ManualChapterAccessCheckerInterface::class));
    // 1.9: the access policy as a port; alias it to your own service to replace it entirely.
    $services->alias(ManualAccessInterface::class, ManualAccess::class);

    // 1.13: the reader of a root (its audiences for the current user) as a port; and which root a request is for.
    $services->set(ManualReaderResolver::class)
        ->arg('$access', service(ManualAccessInterface::class))
        ->arg('$authorization', service('security.authorization_checker')->nullOnInvalid());
    $services->alias(ManualReaderResolverInterface::class, ManualReaderResolver::class);
    $services->set(ManualRootResolver::class)
        ->arg('$security', service('security.helper')->nullOnInvalid());
    // 1.13: `type: nowo_manual` route imports (one per root).
    $services->set(ManualRouteLoader::class)
        ->autoconfigure(false)
        ->arg('$env', param('kernel.environment'))
        ->tag('routing.loader');
    // 1.13: `cache:warmup` fails on an unknown audience (a `directory` from an env var is not read at build time).
    $services->set(AudienceCacheWarmer::class)
        ->autoconfigure(false)
        ->arg('$directory', param('nowo_manual_kit.directory'))
        ->tag('kernel.cache_warmer');

    // Not autoconfigured (REQ-SF-004 rule 7): on Symfony >= 7.3 a #[Route] class is autoconfigured with the
    // routing.controller tag, and the host's `routing.controllers` resource would then register the manual routes
    // without the host importing routes.php (and without its prefix, i.e. at `/` and `/{chapter}`).
    $services->set(ManualController::class)
        ->autoconfigure(false)
        ->tag('controller.service_arguments')
        ->public()
        ->arg('$template', param('nowo_manual_kit.template'))
        ->arg('$groups', param('nowo_manual_kit.groups'))
        ->arg('$devices', service(DeviceViews::class));

    $services->set(ManualLinkExtension::class)
        ->arg('$docsUrl', param('nowo_manual_kit.docs_url'))
        ->arg('$inApp', param('nowo_manual_kit.in_app'))
        ->arg('$layout', param('nowo_manual_kit.layout'))
        ->arg('$packages', service('assets.packages')->nullOnInvalid())
        ->arg('$standaloneLightbox', param('nowo_manual_kit.standalone_lightbox'));

    $services->set(TranslationAudit::class);
    $services->set(CheckTranslationsCommand::class);
    $services->set(StampTranslationCommand::class);
    $services->set(RedirectsCommand::class)
        ->arg('$parameters', service('parameter_bag'));

    // `$translator` (the provider) and the glossary arguments are set by NowoManualKitExtension.
    $services->set(MarkdownTranslator::class)->arg('$translator', null);
    $services->set(UiGlossary::class);
    $services->set(TranslateManualCommand::class);

    // 1.15: the manual search, also without SearchKit (the manual's own search box). `$synonyms`: the optional
    // ManualSynonymsInterface — the host's alias, or SearchKit's synonyms when installed and on (ManualSynonymsPass).
    $services->set(ManualSearch::class)
        ->arg('$synonyms', service(ManualSynonymsInterface::class)->nullOnInvalid());

    if (interface_exists(SearchProviderInterface::class)) {
        $services->set(ManualSearchProvider::class);
    }
};
