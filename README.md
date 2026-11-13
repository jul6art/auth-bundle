<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

Symfony auth bundle
===================

<p align="left">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2.7.0&color=0ea5e9" alt="Version">
</p>

Requirements
------------

* **php ^8.5**
* **symfony ^7.4 || ^8.0**
* **jul6art/core-bundle ^3.10**

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
    symfony/security-bundle symfony/mailer symfony/twig-bundle symfony/expression-language symfony/clock \
    symfony/rate-limiter
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

**The budget of wrong codes (2.5).** Six digits fall in a million attempts, and a firewall's
`login_throttling` counts per (identifier, IP) over a minute: a client that changes its address gets five more.
An account has five wrong codes per quarter of an hour, whatever its address; then even the right code is
refused before it is read, with a 429 `error.login.too_many_attempts`. Every door that checks a code spends it —
the sign-in's second factor (`/api/2fa_check`), the enrolment, the activation and `#[CurrentTotpCode]` (backup
codes, deactivation) — and a right code clears it. Two realms sharing an address keep two budgets.

```yaml
auth:
    api:
        two_factor_throttling:
            max_attempts: 5          # the defaults
            interval: '15 minutes'
            cache_pool: ~            # a filesystem pool of the bundle, "auth.two_factor_throttling.cache"
```

> ⚠️ **Clear the pool between tests.** It outlives the kernel, and a suite whose tests share one account spends
> its budget across them: `self::getContainer()->get('auth.two_factor_throttling.cache')->clear()`. With
> parallel processes, give each its own pools (`framework.cache.prefix_seed` per `TEST_TOKEN`).

Sign-in limiter (optional)
--------------------------

Symfony's `login_throttling` limits a whole firewall: its listener checks the limiter on every passport and
consumes it on every failure, whatever the authenticator. On a firewall that also reads a token (JWT), an
expired or forged token is a failure too — it spends the budget of the client's IP, and once that budget is
gone every request from the IP is refused, valid tokens included. `auth.sign_in_throttling` gives such a
firewall a limiter that counts the sign-in paths, and only them:

```shell
composer require symfony/rate-limiter
```

```yaml
# config/packages/auth.yaml
auth:
    sign_in_throttling:
        paths: ['/api/auth/']            # the prefixes where a request is a sign-in attempt
        max_attempts: 5                  # per identifier and IP; the IP gets five times as many
        interval: '1 minute'
        cache_pool: cache.sign_in_limiter # optional: name your own pool to clear it between tests

# config/packages/security.yaml
security:
    firewalls:
        api:
            # json_login, jwt, refresh_jwt…
            login_throttling:
                limiter: auth.sign_in_throttling.limiter
```

- The budgets are those of Symfony's own limiter: `max_attempts` per identifier and IP, five times as many per
  IP, every identifier together — the second stops a sweep that tries one address per request.
- A successful sign-in clears the identifier's budget only: the IP's survives it, so a client that owns one
  account cannot reset its sweep.
- The keys are hashed with `kernel.secret`: the cache holds no address in the clear.
- Several firewalls may name the same limiter: give it every sign-in path (`/api/auth/`, `/login`) and the web
  form and the API share one budget, instead of adding theirs up.
- ⚠️ The counters outlive a kernel: in tests they are shared by every class of a run. Name a pool and clear it
  before a test signs in. Never an array pool, which forgets at the end of each request.

API failures (optional)
-----------------------

Each authenticator of an API firewall tells its own story when it fails: "Invalid credentials" for a sign-in,
"JWT Refresh Token Not Found" for a refresh token that never existed and "Invalid JWT Refresh Token" for one
that expired, "Expired JWT Token"… — distinctions an application has no reason to publish. And Symfony's
throttling answers a plain 401, so a client cannot tell "wrong password" from "locked out for a minute".
`auth.api_failures` gives every failure of the named firewalls one JSON answer:

```yaml
# config/packages/auth.yaml
auth:
    api_failures:
        firewalls: [api]                          # the default; never a form login, which redirects
        invalid_credentials_code: INVALID_CREDENTIALS
        rate_limited_code: RATE_LIMITED           # the code of your other rate limits, if you have some
```

| Failure | Status | Body |
|---|---|---|
| any (password, unknown or disabled account, refresh token, JWT) | 401 | `{"code": 401, "errorCode": "INVALID_CREDENTIALS", "message": "Invalid credentials."}` |
| the login limiter refuses | 429, `Retry-After` | `{"code": 429, "errorCode": "RATE_LIMITED", "message": "Too many attempts. Try again later."}` |

`code` and `message` keep the shape LexikJWT answers with; `errorCode` is the value a client maps. A request
without a token is not a failure but the entry point's business, and keeps its answer.

Account commands and password services (optional)
--------------------------------------------------

Two commands an application used to copy from the skeleton, each behind its own switch — an application keeps its
own copy until it turns the bundle's on:

```shell
composer require symfony/console
```

```yaml
# config/packages/auth.yaml
auth:
    account:
        user_class: App\Entity\User          # the default: the bundle's own User
        create_user:
            enabled: true
            admin_role: ROLE_SUPER_ADMIN      # the role --admin gives

when@dev:
    auth:
        account:
            dev_login_link:
                enabled: true
                firewall: main                # the firewall whose login_link makes the URL
```

- **`app:user:create <email> [--admin] [--first-name=…] [--last-name=…] [--set <property>=<value>]`**: the password
  is asked hidden, never taken as an argument. The account is written through its own setters — `setFirstName()`,
  `setLastName()` and `setIsActive(true)` when it has them, any other one by `--set` (`--set username=ada`). The
  address is looked up normalised: `Ada@x.test` finds `ada@x.test`. `AccountCreationEvent` lets a listener
  complete the account in the same flush (a personal space, an address taken as confirmed).
- **`app:dev:login-link <email>`** prints a one-time sign-in URL. It and the target of its link,
  `Jul6Art\AuthBundle\Account\Dev\LoginLinkController`, exist **in the `dev` environment only**, whatever the
  configuration says. Route the target under `when@dev`:

```yaml
# config/routes/dev_login_link.yaml
when@dev:
    dev_login_link_check:
        path: /login-link/check
        controller: Jul6Art\AuthBundle\Account\Dev\LoginLinkController
```

`Account\PasswordWriter` (writes a password, ends the account's API sessions when gesdinet is there) and
`Account\PasswordResetMailer` (the forgotten-password e-mail, in the account's language) serve the API sign-in and
admin-bundle's web screens alike. They lived under `Api\Service\` until 2.7: the old names stay aliases until 3.0.

The production firewalls, tested
--------------------------------

`Test\FirewallRulesTestCase` checks what the `prod` environment writes, once per project:

```php
// tests/Architecture/FirewallRulesTest.php
final class FirewallRulesTest extends FirewallRulesTestCase
{
}
```

- a firewall with `form_login`, `json_login` or a custom authenticator has `login_throttling`;
- the firewalls that share the same accounts (provider) share the same `user_checker`;
- with gesdinet installed: `single_use: true` and `hash_tokens.enabled: true`;
- a sign-out path has a route, and that route answers POST only.

A firewall whose custom authenticator checks a long random token — a runner's, a server agent's, a
personal API token — and no typed password is named in `tokenFirewalls()`, with its reason: the
list is what a review sees. It is exempt from `login_throttling` only: the account checker still
stands behind it, so a refusal the checker learns later reaches a personal token too. The configuration is read without booting a production kernel
(core-bundle's `Test\KernelConfiguration`).

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
