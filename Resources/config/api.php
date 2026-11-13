<?php

declare(strict_types=1);

use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Security\RealmRefreshTokens;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The API sign-in (auth.api): loaded only when it is enabled, its packages checked by the extension first.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('Jul6Art\\AuthBundle\\Api\\', '../../Api/')
        ->exclude([
            '../../Api/Account/',
            '../../Api/ApiResource/',
            '../../Api/Realm/Realm.php',
            '../../Api/Http/AuthCodeException.php',
            // The old names of two services that moved to Account/ in 2.8: aliases, registered by the extension.
            '../../Api/Service/PasswordWriter.php',
            '../../Api/Service/PasswordResetMailer.php',
        ]);

    // Innermost decorator of gesdinet's manager (priority 10): its hashing decorator still wraps it.
    $services->set(RealmRefreshTokens::class)
        ->decorate('gesdinet_jwt_refresh_token.refresh_token_manager', null, 10)
        ->args([
            service('.inner'),
            service(Realms::class),
            service('doctrine'),
            param('gesdinet_jwt_refresh_token.default_invalid_batch_size'),
        ]);
};
