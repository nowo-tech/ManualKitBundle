<?php

declare(strict_types=1);

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Command\CheckTranslationsCommand;
use Nowo\ManualKitBundle\Command\StampTranslationCommand;
use Nowo\ManualKitBundle\Command\TranslateManualCommand;
use Nowo\ManualKitBundle\Controller\ManualController;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use Nowo\ManualKitBundle\Search\ManualSearchProvider;
use Nowo\ManualKitBundle\Security\ManualAccess;
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

    $services->set(ManualCatalog::class)
        ->args([param('nowo_manual_kit.directory'), param('nowo_manual_kit.source_locale'), param('nowo_manual_kit.locales')]);

    // `$cache` is the configured pool, set by NowoManualKitExtension.
    $services->set(ManualRenderer::class)
        ->arg('$docsUrl', param('nowo_manual_kit.docs_url'));

    $services->set(ManualAccess::class)
        ->arg('$role', param('nowo_manual_kit.role'))
        ->arg('$adminRole', param('nowo_manual_kit.admin_role'));

    $services->set(ManualController::class)
        ->public()
        ->arg('$template', param('nowo_manual_kit.template'));
    // controller.service_arguments comes from #[AsController] + autoconfigure.

    $services->set(ManualLinkExtension::class)
        ->arg('$docsUrl', param('nowo_manual_kit.docs_url'))
        ->arg('$inApp', param('nowo_manual_kit.in_app'))
        ->arg('$layout', param('nowo_manual_kit.layout'))
        ->arg('$packages', service('assets.packages')->nullOnInvalid())
        ->arg('$standaloneLightbox', param('nowo_manual_kit.standalone_lightbox'));

    $services->set(TranslationAudit::class);
    $services->set(CheckTranslationsCommand::class);
    $services->set(StampTranslationCommand::class);

    // `$translator` (the provider) and the glossary arguments are set by NowoManualKitExtension.
    $services->set(MarkdownTranslator::class)->arg('$translator', null);
    $services->set(UiGlossary::class);
    $services->set(TranslateManualCommand::class);

    if (interface_exists(SearchProviderInterface::class)) {
        $services->set(ManualSearchProvider::class);
    }
};
