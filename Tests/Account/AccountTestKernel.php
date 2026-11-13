<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Account;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\AuthBundle\AuthBundle;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Tests\Account\Fixtures\Member;
use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application with an account entity of its own, a firewall that makes sign-in links, and the account commands
 * as the configuration says — in the environment the test asks for.
 */
final class AccountTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $account the "auth.account" configuration
     */
    public function __construct(
        string $environment,
        private readonly array $account,
    ) {
        parent::__construct($environment, false);
    }

    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
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

    private function buildDir(): string
    {
        return \sprintf('%s/jul6art-auth-bundle-account-tests/%s/%s', sys_get_temp_dir(), substr(md5(serialize($this->account)), 0, 12), $this->environment);
    }

    private function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'auth-bundle-account-tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'mappings' => [
                    'Account' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures',
                        'prefix' => 'Jul6Art\AuthBundle\Tests\Account\Fixtures',
                        'is_bundle' => false,
                    ],
                    // The bundle's own User: the entity of the skeleton's web, admin and API modes.
                    'AuthBundle' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__, 2).'/Entity',
                        'prefix' => 'Jul6Art\AuthBundle\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('security', [
            'password_hashers' => [Member::class => ['algorithm' => 'plaintext'], User::class => ['algorithm' => 'plaintext']],
            'providers' => ['members' => ['entity' => ['class' => Member::class, 'property' => 'email']]],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'members',
                    'login_link' => ['check_route' => 'dev_login_link_check', 'signature_properties' => ['id', 'password']],
                ],
            ],
        ]);

        $container->loadFromExtension('auth', ['account' => $this->account]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('dev_login_link_check', '/login-link/check');
    }
}
