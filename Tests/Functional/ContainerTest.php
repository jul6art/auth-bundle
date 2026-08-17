<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Functional;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Manager\UserManager;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Jul6Art\AuthBundle\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Boots a real kernel with CoreBundle and AuthBundle registered together.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    /**
     * The whole container used to fail to compile here, because services.yaml aliased
     * the interfaces onto services that were never registered.
     */
    public function testTheContainerCompilesAndResolvesTheInterfaces(): void
    {
        $container = $this->boot();

        self::assertInstanceOf(UserRepository::class, $container->get(UserRepositoryInterface::class));
        self::assertInstanceOf(UserManager::class, $container->get(UserManagerInterface::class));
    }

    public function testTheInterfaceAliasesShareTheConcreteInstances(): void
    {
        $container = $this->boot();

        self::assertSame($container->get(UserRepository::class), $container->get(UserRepositoryInterface::class));
        self::assertSame($container->get(UserManager::class), $container->get(UserManagerInterface::class));
    }

    public function testTheConfigurationIsExposedAsContainerParameters(): void
    {
        self::assertTrue($this->boot()->getParameter('auth.enabled'));
        self::assertFalse($this->boot('test', ['enabled' => false])->getParameter('auth.enabled'));
    }

    public function testCoreBundleParametersAreStillExposed(): void
    {
        self::assertFalse($this->boot()->getParameter('core.email_debug'));
    }
}
