<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

Symfony auth bundle
===================

<p align="left">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2.2.4&color=0ea5e9" alt="Version">
</p>

Requirements
------------

* **php ^8.5**
* **symfony ^7.4 || ^8.0**
* **jul6art/core-bundle ^2.0**

Installation
------------

```shell
composer require jul6art/auth-bundle
```

Map the bundle entities in your Doctrine configuration:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            AuthBundle:
                type: attribute
                dir: '%kernel.project_dir%/vendor/jul6art/auth-bundle/Entity'
                prefix: 'Jul6Art\AuthBundle\Entity'
                is_bundle: false
```

Usage
-----

The bundle registers its repository and its manager, and aliases both interfaces
onto them, so they can be injected directly:

```php
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;

public function __construct(
    private UserManagerInterface $userManager,
    private UserRepositoryInterface $userRepository,
) {
}
```

`UserRepository` implements `PasswordUpgraderInterface`, so it can be wired as the
password upgrader of your firewall.

Configuration
-------------

```yaml
# config/packages/auth.yaml
auth:
    enabled: true
```

The option is exposed as the `auth.enabled` container parameter.

API sign-in (optional)
----------------------

An API Platform application can sign its accounts in through the bundle: a JSON sign-in, the second factor
(TOTP and ten backup codes, an enrolment at the first sign-in), the access token and the refresh token in
`HttpOnly` cookies, "stay signed in", the forgotten password, the change of password, and the second factor
switched on and off from the account's page. It is **off by default**, and its packages are suggested, not
required — the bundle's other users get nothing new:

```shell
composer require api-platform/core lexik/jwt-authentication-bundle gesdinet/jwt-refresh-token-bundle \
    scheb/2fa-bundle scheb/2fa-totp scheb/2fa-backup-code endroid/qr-code symfonycasts/reset-password-bundle \
    symfony/security-bundle symfony/mailer symfony/twig-bundle symfony/expression-language symfony/clock
```

One kernel may serve several applications, each on its own host and with its own base: each declares a
**realm** — its firewalls, its classes, the name its second factor and its e-mails carry. A refresh token, a
reset link of one realm never opens another.

```yaml
# config/packages/auth.yaml
auth:
    api:
        # Ignored in "prod", where every account always owes its second factor.
        two_factor_required: '%env(bool:TWO_FACTOR_REQUIRED)%'
        realms:
            shop:
                firewalls: [shop_login, shop_public, shop_api]
                user_class: App\Entity\User                     # implements Api\Account\ApiAccountInterface
                login_record_class: App\Entity\LoginRecord       # implements Api\Account\LoginRecordInterface
                refresh_token_class: App\Entity\RefreshToken     # extends gesdinet's RefreshToken
                reset_password_request_class: App\Entity\ResetPasswordRequest
                issuer: Shop
                mail:
                    from: '%env(MAILER_FROM)%'
                    reset_links:
                        en: 'https://shop.example.org/password/{token}'
                        fr: 'https://shop.example.org/mot-de-passe/{token}'
```

- `Api\Account\TwoFactorAccountTrait` carries the second factor's columns (the secret encrypted at rest with
  core-bundle's `encrypted_string`).
- The firewalls use the bundle's handlers: `LoginSuccessHandler`, `LoginFailureHandler`, `TwoFactorSuccessHandler`,
  `TwoFactorFailureHandler`, `TwoFactorRequiredHandler`, `RefreshFailureHandler`, `UserChecker`, `ApiEntryPoint`
  (namespace `Jul6Art\AuthBundle\Api\Security`). The bundle sets scheb's `two_factor_condition` and
  symfonycasts' `request_password_repository` itself.
- Import the enrolment's routes: `resource: '@AuthBundle/Resources/config/routes/api.php'`.
- The reset link is always the realm's, never built from the request's host — which a visitor chooses.
- An application that serves several realms binds each access token to its realm (an audience), as it binds
  each host to its firewall: the bundle cannot know which host is whose.

Quality assurance
-----------------

```shell
composer qa           # coding standards, Rector, static analysis and tests
composer test         # PHPUnit
composer phpstan      # PHPStan, level max
composer cs           # PHP-CS-Fixer, writes the fixes
composer rector       # Rector, writes the fixes
```

License
-------

The Auth Bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
