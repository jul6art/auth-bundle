<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Manager\Traits;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Manager\Traits\UserManagerAwareTrait;
use Jul6Art\AuthBundle\Tests\Fixtures\UserManagerAwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\Attribute\Required;

#[CoversTrait(UserManagerAwareTrait::class)]
final class UserManagerAwareTraitTest extends TestCase
{
    /**
     * The "@required" annotation is gone, so the attribute is the only thing making
     * the container call this setter.
     */
    public function testTheSetterIsMarkedRequired(): void
    {
        $attributes = new \ReflectionMethod(UserManagerAwareService::class, 'setUserManager')->getAttributes(Required::class);

        self::assertCount(1, $attributes);
    }

    public function testThePropertyIsTyped(): void
    {
        $type = new \ReflectionProperty(UserManagerAwareService::class, 'userManager')->getType();

        self::assertNotNull($type);
        self::assertSame(UserManagerInterface::class, (string) $type);
    }

    public function testTheSetterStoresTheManager(): void
    {
        $service = new UserManagerAwareService();
        $manager = self::createStub(UserManagerInterface::class);

        $service->setUserManager($manager);

        self::assertSame($manager, $service->userManager());
    }

    public function testTheManagerIsOnlyAvailableOnceTheSetterRan(): void
    {
        $service = new UserManagerAwareService();

        $this->expectException(\Error::class);
        $this->expectExceptionMessageIsOrContains('must not be accessed before initialization');

        $manager = $service->userManager();

        self::fail(\sprintf('Reading the manager before injection should fail, got "%s".', $manager::class));
    }
}
