<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the API sign-in, to import from the application's routing:
 *
 *     auth_api:
 *         resource: '@AuthBundle/Resources/config/routes/api.php'
 *
 * The sign-in's other routes (`/api/login`, `/api/2fa_check`, `/api/token/refresh`, `/api/logout`) are those the
 * application declares for its firewalls; the API resources come through API Platform's mapping.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(__DIR__.'/../../../Api/Controller/', 'attribute');
};
