<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\DependencyInjection;

use ApiPlatform\State\ProcessorInterface;
use Endroid\QrCode\Builder\Builder;
use Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle;
use Jul6Art\AuthBundle\Api\Security\RealmResetPasswordRequests;
use Jul6Art\AuthBundle\Api\Security\TwoFactorPolicy;
use Jul6Art\AuthBundle\Api\Security\TwoFactorThrottle;
use Jul6Art\AuthBundle\Security\ApiAuthenticationFailureNormalizer;
use Jul6Art\AuthBundle\Security\SignInRateLimiter;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle;

/**
 * Class AuthExtension.
 *
 * @phpstan-type AuthConfig array{enabled: bool}
 */
class AuthExtension extends Extension implements PrependExtensionInterface
{
    /** What the API sign-in needs, beyond the bundle's own requirements: its packages are suggested. */
    private const array API_PACKAGES = [
        'api-platform/core' => ProcessorInterface::class,
        'lexik/jwt-authentication-bundle' => LexikJWTAuthenticationBundle::class,
        'gesdinet/jwt-refresh-token-bundle' => GesdinetJWTRefreshTokenBundle::class,
        'scheb/2fa-bundle' => SchebTwoFactorBundle::class,
        'scheb/2fa-totp' => TotpAuthenticatorInterface::class,
        'scheb/2fa-backup-code' => BackupCodeInterface::class,
        'symfony/rate-limiter' => RateLimiterFactory::class,
        'endroid/qr-code' => Builder::class,
        'symfonycasts/reset-password-bundle' => SymfonyCastsResetPasswordBundle::class,
        'symfony/security-bundle' => Security::class,
        'symfony/mailer' => MailerInterface::class,
        'symfony/twig-bridge' => TemplatedEmail::class,
        'symfony/clock' => NativeClock::class,
        'symfony/expression-language' => ExpressionLanguage::class,
    ];

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);
        $this->loadSignInThrottling($config['sign_in_throttling'] ?? null, $container);
        $this->loadApiFailures($config['api_failures'] ?? null, $container);

        $api = \is_array($config['api'] ?? null) ? $config['api'] : [];
        if (true !== ($api['enabled'] ?? false)) {
            return;
        }

        foreach (self::API_PACKAGES as $package => $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new \LogicException(\sprintf('The API sign-in (auth.api) needs "%s": composer require %s.', $package, $package));
            }
        }

        // A boolean, or the placeholder of an environment variable (`%env(bool:…)%`), resolved at runtime.
        $twoFactorRequired = $api['two_factor_required'] ?? true;
        $realms = $api['realms'] ?? [];
        if ((!\is_bool($twoFactorRequired) && !\is_string($twoFactorRequired)) || !\is_array($realms)) {
            throw new \LogicException('The configuration of auth.api could not be read.');
        }

        $container->setParameter('auth.api.two_factor_required', $twoFactorRequired);
        $container->setParameter('auth.api.realms', $realms);

        new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'))->load('api.php');
        $this->loadTwoFactorThrottling($api['two_factor_throttling'] ?? null, $container);
    }

    /**
     * The account's budget of wrong codes ({@see TwoFactorThrottle}). Always on with the API sign-in: six
     * digits fall in a million attempts, and the firewall's limiter counts per address.
     */
    private function loadTwoFactorThrottling(mixed $config, ContainerBuilder $container): void
    {
        $config = \is_array($config) ? $config : [];
        $maxAttempts = $config['max_attempts'] ?? 5;
        $interval = $config['interval'] ?? '15 minutes';
        $pool = $config['cache_pool'] ?? null;
        if (!\is_int($maxAttempts) || !\is_string($interval) || (null !== $pool && !\is_string($pool))) {
            throw new \LogicException('The configuration of auth.api.two_factor_throttling could not be read.');
        }

        if (null === $pool) {
            $pool = 'auth.two_factor_throttling.cache';
            $container->setDefinition($pool, new ChildDefinition('cache.adapter.filesystem'))->addTag('cache.pool');
        }

        $container->register('auth.two_factor_throttling.storage', CacheStorage::class)
            ->setArguments([new Reference($pool)]);
        $container->register('auth.two_factor_throttling.limiter', RateLimiterFactory::class)
            ->setArguments([
                ['id' => 'auth_two_factor', 'policy' => 'fixed_window', 'limit' => $maxAttempts, 'interval' => $interval],
                new Reference('auth.two_factor_throttling.storage'),
                new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
        $container->register(TwoFactorThrottle::class, TwoFactorThrottle::class)
            ->setArguments([new Reference('auth.two_factor_throttling.limiter'), '%kernel.secret%']);
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        foreach ($this->resolveConfig($container) as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        if (!$this->apiEnabled($container)) {
            return;
        }

        // The resources of the sign-in's routes, and the bundle's own pieces in the libraries it plugs into.
        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig('api_platform', ['mapping' => ['paths' => [__DIR__.'/../Api/ApiResource']]]);
        }

        if ($container->hasExtension('scheb_two_factor')) {
            $container->prependExtensionConfig('scheb_two_factor', ['two_factor_condition' => TwoFactorPolicy::class]);
        }

        if ($container->hasExtension('symfonycasts_reset_password')) {
            $container->prependExtensionConfig('symfonycasts_reset_password', ['request_password_repository' => RealmResetPasswordRequests::class]);
        }
    }

    /**
     * The limiter of `auth.sign_in_throttling`, its two budgets and the pool they count in.
     *
     * ⚠️ The budgets are those of Symfony's own `login_throttling`: `max_attempts` per identifier and
     * IP, five times as many per IP. A fixed window, so the 429 can tell when it ends.
     */
    private function loadSignInThrottling(mixed $config, ContainerBuilder $container): void
    {
        if (!\is_array($config) || true !== ($config['enabled'] ?? false)) {
            return;
        }

        if (!class_exists(RateLimiterFactory::class)) {
            throw new \LogicException('The sign-in limiter (auth.sign_in_throttling) needs "symfony/rate-limiter": composer require symfony/rate-limiter.');
        }

        $paths = $config['paths'] ?? [];
        $maxAttempts = $config['max_attempts'] ?? 5;
        $interval = $config['interval'] ?? '1 minute';
        $pool = $config['cache_pool'] ?? null;
        if (!\is_array($paths) || !\is_int($maxAttempts) || !\is_string($interval) || (null !== $pool && !\is_string($pool))) {
            throw new \LogicException('The configuration of auth.sign_in_throttling could not be read.');
        }

        if (null === $pool) {
            $pool = 'auth.sign_in_throttling.cache';
            $container->setDefinition($pool, new ChildDefinition('cache.adapter.filesystem'))->addTag('cache.pool');
        }

        $container->register('auth.sign_in_throttling.storage', CacheStorage::class)
            ->setArguments([new Reference($pool)]);

        foreach (['global' => 5 * $maxAttempts, 'local' => $maxAttempts] as $scope => $limit) {
            $container->register('auth.sign_in_throttling.'.$scope, RateLimiterFactory::class)
                ->setArguments([
                    ['id' => 'auth_sign_in_'.$scope, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => $interval],
                    new Reference('auth.sign_in_throttling.storage'),
                    // The lock of the application when it has one, as `framework.rate_limiter` does.
                    new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
        }

        $container->register('auth.sign_in_throttling.limiter', SignInRateLimiter::class)
            ->setArguments([
                new Reference('auth.sign_in_throttling.global'),
                new Reference('auth.sign_in_throttling.local'),
                array_values($paths),
                '%kernel.secret%',
            ]);
        $container->setAlias(SignInRateLimiter::class, 'auth.sign_in_throttling.limiter');
    }

    /**
     * The listener of `auth.api_failures`. A global listener: SecurityBundle relays it to the dispatcher
     * of every firewall, and the listener keeps to the firewalls it was given.
     */
    private function loadApiFailures(mixed $config, ContainerBuilder $container): void
    {
        if (!\is_array($config) || true !== ($config['enabled'] ?? false)) {
            return;
        }

        if (!class_exists(LoginFailureEvent::class)) {
            throw new \LogicException('The API failures (auth.api_failures) need "symfony/security-bundle": composer require symfony/security-bundle.');
        }

        $firewalls = $config['firewalls'] ?? [];
        $invalidCredentials = $config['invalid_credentials_code'] ?? null;
        $rateLimited = $config['rate_limited_code'] ?? null;
        if (!\is_array($firewalls) || !\is_string($invalidCredentials) || !\is_string($rateLimited)) {
            throw new \LogicException('The configuration of auth.api_failures could not be read.');
        }

        $container->register(ApiAuthenticationFailureNormalizer::class, ApiAuthenticationFailureNormalizer::class)
            ->setArguments([array_values($firewalls), $invalidCredentials, $rateLimited])
            ->addTag('kernel.event_listener', ['event' => LoginFailureEvent::class, 'method' => '__invoke']);
    }

    private function apiEnabled(ContainerBuilder $container): bool
    {
        $enabled = false;
        foreach ($container->getExtensionConfig($this->getAlias()) as $config) {
            if (!\array_key_exists('api', $config)) {
                continue;
            }

            // `api: ~`, `api: true` or a table without `enabled`: on, as `canBeEnabled()` reads it.
            $api = $config['api'];
            $enabled = null === $api || true === $api || (\is_array($api) && true === ($api['enabled'] ?? true));
        }

        return $enabled;
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return AuthConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        // `enabled` only: the rest of the tree is processed by load(), where an `%env(bool:…)%` placeholder
        // stays one — resolved here, it would be a string a boolean node refuses.
        $enabled = array_map(
            static fn (array $config): array => \array_key_exists('enabled', $config) ? ['enabled' => $config['enabled']] : [],
            $container->getExtensionConfig($this->getAlias()),
        );
        $configs = $container->resolveEnvPlaceholders($enabled, true);
        $config = $this->processConfiguration(new Configuration(), \is_array($configs) ? $configs : []);

        return [
            'enabled' => false !== ($config['enabled'] ?? true),
        ];
    }
}
