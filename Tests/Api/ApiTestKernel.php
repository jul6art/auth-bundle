<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle;
use Jul6Art\AuthBundle\Api\Security\ApiEntryPoint;
use Jul6Art\AuthBundle\Api\Security\LoginFailureHandler;
use Jul6Art\AuthBundle\Api\Security\LoginSuccessHandler;
use Jul6Art\AuthBundle\Api\Security\RefreshFailureHandler;
use Jul6Art\AuthBundle\Api\Security\TwoFactorFailureHandler;
use Jul6Art\AuthBundle\Api\Security\TwoFactorRequiredHandler;
use Jul6Art\AuthBundle\Api\Security\TwoFactorSuccessHandler;
use Jul6Art\AuthBundle\Api\Security\UserChecker;
use Jul6Art\AuthBundle\AuthBundle;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\RefreshToken;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\ResetPasswordRequestRepository;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmB;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Psr\Log\NullLogger;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle;

/**
 * Two applications in one kernel, as a platform that hosts them runs them: realm A on `a.test`, realm B on
 * `b.test`, each with its accounts in its own base (two SQLite files), its firewalls, its e-mails.
 */
final class ApiTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SECRET = 'a-secret-only-the-tests-know-and-long-enough-for-hs256';

    public function __construct(
        private readonly bool $twoFactorRequired,
    ) {
        parent::__construct('test', true);
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new TwigBundle();
        yield new ApiPlatformBundle();
        yield new LexikJWTAuthenticationBundle();
        yield new GesdinetJWTRefreshTokenBundle();
        yield new SchebTwoFactorBundle();
        yield new SymfonyCastsResetPasswordBundle();
        yield new CoreBundle();
        yield new AuthBundle();
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->buildDir().'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->buildDir().'/log';
    }

    public function databasePath(string $realm): string
    {
        return $this->buildDir().'/'.$realm.'.sqlite';
    }

    private function buildDir(): string
    {
        return \sprintf('%s/jul6art-auth-bundle-api-tests/%s', sys_get_temp_dir(), $this->twoFactorRequired ? 'required' : 'optional');
    }

    private function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'auth-bundle-api-tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'mailer' => ['dsn' => 'null://null'],
            'validation' => ['enabled' => true],
            'serializer' => ['enabled' => true],
            'property_access' => ['enabled' => true],
        ]);

        $container->loadFromExtension('twig', []);

        $container->loadFromExtension('core', ['encryption_key' => base64_encode(str_repeat('k', 32))]);

        $connections = [];
        $managers = [];
        foreach (['a' => 'RealmA', 'b' => 'RealmB'] as $realm => $directory) {
            $connections[$realm] = ['driver' => 'pdo_sqlite', 'path' => $this->databasePath($realm)];
            $managers[$realm] = [
                'connection' => $realm,
                'mappings' => [
                    $directory => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures/'.$directory,
                        'prefix' => 'Jul6Art\AuthBundle\Tests\Api\Fixtures\\'.$directory,
                        'is_bundle' => false,
                    ],
                    'Account'.$directory => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures/Shared',
                        'prefix' => 'Jul6Art\AuthBundle\Tests\Api\Fixtures\Shared',
                        'is_bundle' => false,
                    ],
                    'Gesdinet'.$directory => [
                        'type' => 'xml',
                        'dir' => '%kernel.project_dir%/vendor/gesdinet/jwt-refresh-token-bundle/config/doctrine',
                        'prefix' => 'Gesdinet\JWTRefreshTokenBundle\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ];
        }

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'default_connection' => 'a',
                'connections' => $connections,
                'types' => [EncryptedStringType::NAME => EncryptedStringType::class],
            ],
            'orm' => [
                'default_entity_manager' => 'a',
                'controller_resolver' => ['auto_mapping' => false],
                'entity_managers' => $managers,
            ],
        ]);

        $container->loadFromExtension('api_platform', [
            'title' => 'auth-bundle tests',
            'formats' => ['json' => ['application/json']],
            'docs_formats' => ['json' => ['application/json']],
        ]);

        $container->loadFromExtension('lexik_jwt_authentication', [
            'secret_key' => self::SECRET,
            'encoder' => ['signature_algorithm' => 'HS256'],
            'token_ttl' => 900,
            'user_id_claim' => 'email',
            'token_extractors' => [
                'authorization_header' => ['enabled' => true],
                'cookie' => ['enabled' => true, 'name' => '__Host-access_token'],
            ],
            'set_cookies' => ['__Host-access_token' => ['samesite' => 'strict', 'path' => '/', 'domain' => null, 'secure' => true, 'httpOnly' => true]],
            'remove_token_from_body_when_cookies_used' => true,
        ]);

        $container->loadFromExtension('gesdinet_jwt_refresh_token', [
            'refresh_token_class' => RefreshToken::class,
            'ttl' => 604800,
            'single_use' => true,
            'token_parameter_name' => 'refresh_token',
            'hash_tokens' => ['enabled' => true, 'accept_stored_in_the_clear' => false],
            'cookie' => ['enabled' => true, 'same_site' => 'strict', 'path' => '/api', 'http_only' => true, 'secure' => true, 'remove_token_from_body' => true],
        ]);

        $container->loadFromExtension('scheb_two_factor', [
            'security_tokens' => [
                UsernamePasswordToken::class,
                PostAuthenticationToken::class,
            ],
            'totp' => ['enabled' => true],
            'backup_codes' => ['enabled' => true],
        ]);

        $container->loadFromExtension('symfonycasts_reset_password', ['lifetime' => 3600, 'throttle_limit' => 900]);

        $firewalls = ['dev' => ['pattern' => '^/_', 'security' => false]];
        $realms = [];
        foreach (['a' => 'Jul6Art\\AuthBundle\\Tests\\Api\\Fixtures\\RealmA', 'b' => 'Jul6Art\\AuthBundle\\Tests\\Api\\Fixtures\\RealmB'] as $realm => $namespace) {
            $host = '^'.$realm.'\.test$';
            $firewalls[$realm.'_login'] = [
                'host' => $host,
                'pattern' => '^/api/(login|2fa_check|2fa_cancel|enrolment)$',
                'provider' => $realm,
                'user_checker' => UserChecker::class,
                'entry_point' => ApiEntryPoint::class,
                'json_login' => [
                    'check_path' => 'api_login',
                    'username_path' => 'email',
                    'password_path' => 'password',
                    'success_handler' => LoginSuccessHandler::class,
                    'failure_handler' => LoginFailureHandler::class,
                ],
                'two_factor' => [
                    'check_path' => '2fa_login_check',
                    'auth_form_path' => '2fa_login_check',
                    'post_only' => true,
                    'auth_code_parameter_name' => 'code',
                    'prepare_on_login' => true,
                    'prepare_on_access_denied' => true,
                    'authentication_required_handler' => TwoFactorRequiredHandler::class,
                    'success_handler' => TwoFactorSuccessHandler::class,
                    'failure_handler' => TwoFactorFailureHandler::class,
                ],
                'logout' => ['path' => 'api_login_cancel'],
            ];
            $firewalls[$realm.'_public'] = ['host' => $host, 'pattern' => '^/api/password/', 'stateless' => true, 'security' => false];
            $firewalls[$realm.'_api'] = [
                'host' => $host,
                'pattern' => '^/api',
                'stateless' => true,
                'provider' => $realm,
                'user_checker' => UserChecker::class,
                'entry_point' => ApiEntryPoint::class,
                'jwt' => [],
                'refresh_jwt' => ['check_path' => 'gesdinet_jwt_refresh_token', 'failure_handler' => RefreshFailureHandler::class],
                'logout' => ['path' => 'api_logout'],
            ];

            $realms[$realm] = [
                'firewalls' => [$realm.'_login', $realm.'_public', $realm.'_api'],
                'user_class' => $namespace.'\Account',
                'login_record_class' => $namespace.'\LoginRecord',
                'refresh_token_class' => $namespace.'\RefreshToken',
                'reset_password_request_class' => $namespace.'\ResetPasswordRequest',
                'issuer' => 'Realm '.strtoupper($realm),
                'mail' => [
                    'from' => 'no-reply@'.$realm.'.test',
                    'reset_links' => ['en' => 'https://'.$realm.'.test/password/{token}', 'fr' => 'https://'.$realm.'.test/mot-de-passe/{token}'],
                ],
            ];
        }

        $container->loadFromExtension('security', [
            'password_hashers' => [PasswordAuthenticatedUserInterface::class => ['algorithm' => 'auto', 'cost' => 4, 'time_cost' => 3, 'memory_cost' => 10]],
            'providers' => [
                'a' => ['entity' => ['class' => Account::class, 'property' => 'email', 'manager_name' => 'a']],
                'b' => ['entity' => ['class' => RealmB\Account::class, 'property' => 'email', 'manager_name' => 'b']],
            ],
            'firewalls' => $firewalls,
        ]);

        $container->loadFromExtension('auth', [
            'api' => [
                'two_factor_required' => $this->twoFactorRequired,
                'realms' => $realms,
            ],
        ]);

        // Silent: the refusals the tests provoke are not news.
        $container->register('logger', NullLogger::class);

        // SecurityBundle drops its expression language when the package is a dev dependency of the root — here,
        // of the bundle itself; an application requires it, and API Platform's `security` reads it.
        $container->register('security.expression_language', ExpressionLanguage::class)
            ->setArguments([new Reference('cache.security_expression_language', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);

        $container->register(ResetPasswordRequestRepository::class, ResetPasswordRequestRepository::class)
            ->setAutowired(true)
            ->addTag('doctrine.repository_service');
        $container->register(RealmB\ResetPasswordRequestRepository::class, RealmB\ResetPasswordRequestRepository::class)
            ->setAutowired(true)
            ->addTag('doctrine.repository_service');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('api_login', '/api/login')->methods(['POST']);
        $routes->add('2fa_login_check', '/api/2fa_check')->methods(['POST']);
        $routes->add('api_login_cancel', '/api/2fa_cancel')->methods(['POST']);
        $routes->add('gesdinet_jwt_refresh_token', '/api/token/refresh')->methods(['POST']);
        $routes->add('api_logout', '/api/logout')->methods(['POST']);
        $routes->import('@AuthBundle/Resources/config/routes/api.php');
        $routes->import('.', 'api_platform')->prefix('/api');
    }
}
