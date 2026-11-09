<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\DependencyInjection;

use ApiPlatform\State\ProcessorInterface;
use Endroid\QrCode\Builder\Builder;
use Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle;
use Jul6Art\AuthBundle\Api\Security\RealmResetPasswordRequests;
use Jul6Art\AuthBundle\Api\Security\TwoFactorPolicy;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Mailer\MailerInterface;
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
