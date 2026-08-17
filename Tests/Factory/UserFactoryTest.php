<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Factory;

use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Factory\UserFactory;
use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserFactory::class)]
final class UserFactoryTest extends TestCase
{
    public function testItImplementsTheCoreBundleContract(): void
    {
        self::assertTrue(new \ReflectionClass(UserFactory::class)->implementsInterface(FactoryInterface::class));
    }

    public function testItBuildsABlankUser(): void
    {
        $user = UserFactory::create();

        self::assertSame(User::class, $user::class);
        self::assertNull($user->getEmail());
        self::assertNull($user->getId());
    }

    public function testItBuildsADistinctInstanceEveryTime(): void
    {
        self::assertNotSame(UserFactory::create(), UserFactory::create());
    }

    public function testItRejectsAnyArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('don\'t need arguments');

        UserFactory::create('unexpected');
    }
}
