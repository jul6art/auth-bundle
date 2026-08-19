<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2&color=orange" alt="Version">
</p>

jul6art/auth-bundle
===================
Symfony auth bundle
-------------------

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
