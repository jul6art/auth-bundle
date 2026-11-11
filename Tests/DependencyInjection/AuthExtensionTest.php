<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\DependencyInjection;

use Jul6Art\AuthBundle\DependencyInjection\AuthExtension;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Manager\UserManager;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Jul6Art\AuthBundle\Repository\UserRepository;
use Jul6Art\AuthBundle\Security\SignInRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(AuthExtension::class)]
final class AuthExtensionTest extends TestCase
{
    /**
     * services.yaml used to alias the two interfaces onto services nobody ever
     * registered, which made the container fail to compile.
     */
    public function testLoadRegistersTheServicesTheAliasesPointAt(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition(UserRepository::class));
        self::assertTrue($container->hasDefinition(UserManager::class));

        self::assertTrue($container->hasAlias(UserRepositoryInterface::class));
        self::assertTrue($container->hasAlias(UserManagerInterface::class));

        self::assertSame(UserRepository::class, (string) $container->getAlias(UserRepositoryInterface::class));
        self::assertSame(UserManager::class, (string) $container->getAlias(UserManagerInterface::class));
    }

    public function testTheRepositoryIsTaggedAsADoctrineRepository(): void
    {
        $definition = $this->load()->getDefinition(UserRepository::class);

        self::assertArrayHasKey('doctrine.repository_service', $definition->getTags());

        $registry = $definition->getArgument(0);
        self::assertInstanceOf(Reference::class, $registry);
        self::assertSame('doctrine', (string) $registry);
    }

    public function testTheManagerReceivesTheRepository(): void
    {
        $calls = $this->load()->getDefinition(UserManager::class)->getMethodCalls();

        self::assertSame(['setUserRepository'], array_column($calls, 0));
    }

    public function testEveryRegisteredClassExists(): void
    {
        foreach ($this->load()->getDefinitions() as $id => $definition) {
            if ('service_container' === $id) {
                continue;
            }

            $class = $definition->getClass() ?? $id;
            self::assertTrue(class_exists($class), \sprintf('Service "%s" points at missing class "%s".', $id, $class));
        }
    }

    public function testPrependExposesTheConfigurationAsParameters(): void
    {
        self::assertTrue($this->prepend([])->getParameter('auth.enabled'));
    }

    public function testPrependExposesTheDisabledFlag(): void
    {
        self::assertFalse($this->prepend(['enabled' => false])->getParameter('auth.enabled'));
    }

    /**
     * An application reads the switch of the second factor from its environment: resolved too early, the
     * placeholder became a string, which the boolean node refused — the kernel did not compile.
     */
    public function testPrependLeavesAnEnvironmentSwitchOfTheApiSignInToLoad(): void
    {
        $container = $this->prepend(['api' => ['two_factor_required' => '%env(bool:TWO_FACTOR_REQUIRED)%']]);

        self::assertTrue($container->getParameter('auth.enabled'));
    }

    private function containerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.bundles' => [],
            'kernel.environment' => 'test',
        ]));
    }

    public function testTheSignInLimiterIsNotRegisteredUnlessAsked(): void
    {
        self::assertFalse($this->load()->hasDefinition('auth.sign_in_throttling.limiter'));
    }

    /**
     * The budgets of Symfony's own `login_throttling`: N per identifier and IP, 5×N per IP.
     */
    public function testTheSignInLimiterGetsItsPathsBudgetsAndADedicatedPool(): void
    {
        $container = $this->containerBuilder();
        new AuthExtension()->load([['sign_in_throttling' => ['paths' => ['/api/auth/'], 'max_attempts' => 4]]], $container);

        $limiter = $container->getDefinition('auth.sign_in_throttling.limiter');
        self::assertSame(SignInRateLimiter::class, $limiter->getClass());
        self::assertSame(['/api/auth/'], $limiter->getArgument(2));
        self::assertSame('%kernel.secret%', $limiter->getArgument(3));
        self::assertSame('auth.sign_in_throttling.limiter', (string) $container->getAlias(SignInRateLimiter::class));

        foreach (['global' => 20, 'local' => 4] as $scope => $limit) {
            $budget = $container->getDefinition('auth.sign_in_throttling.'.$scope)->getArgument(0);
            self::assertIsArray($budget);
            self::assertSame(['id' => 'auth_sign_in_'.$scope, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], $budget);
        }

        $pool = $container->getDefinition('auth.sign_in_throttling.cache');
        self::assertArrayHasKey('cache.pool', $pool->getTags());
        $storagePool = $container->getDefinition('auth.sign_in_throttling.storage')->getArgument(0);
        self::assertInstanceOf(Reference::class, $storagePool);
        self::assertSame('auth.sign_in_throttling.cache', (string) $storagePool);
    }

    /**
     * An application names its own pool to clear it between tests.
     */
    public function testTheSignInLimiterCountsInThePoolTheApplicationNames(): void
    {
        $container = $this->containerBuilder();
        new AuthExtension()->load([['sign_in_throttling' => ['paths' => ['/api/auth/'], 'cache_pool' => 'cache.api_auth_limiter']]], $container);

        self::assertFalse($container->hasDefinition('auth.sign_in_throttling.cache'));
        $storagePool = $container->getDefinition('auth.sign_in_throttling.storage')->getArgument(0);
        self::assertInstanceOf(Reference::class, $storagePool);
        self::assertSame('cache.api_auth_limiter', (string) $storagePool);
    }

    private function load(): ContainerBuilder
    {
        $container = $this->containerBuilder();
        new AuthExtension()->load([], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function prepend(array $config): ContainerBuilder
    {
        $container = $this->containerBuilder();
        $extension = new AuthExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('auth', $config);

        $extension->prepend($container);

        return $container;
    }
}
