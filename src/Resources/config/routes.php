<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The manual's routes (`nowo_manual_index`, `nowo_manual_chapter`, `nowo_manual_chapter_md`, `nowo_manual_image`).
 * Import them with a prefix:
 *
 *     nowo_manual_kit:
 *         resource: '@NowoManualKitBundle/Resources/config/routes.php'
 *         prefix: /manual
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Controller/', 'attribute');
};
