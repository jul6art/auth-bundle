<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\DependencyInjection;

use Jul6Art\AuthBundle\DependencyInjection\AuthExtension;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Manager\UserManager;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Jul6Art\AuthBundle\Repository\UserRepository;
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

    private function containerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.bundles' => [],
            'kernel.environment' => 'test',
        ]));
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
