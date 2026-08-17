<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Entity\Interfaces\UserInterface;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    /**
     * getPassword() moved out of the base UserInterface in Symfony 5.3, so the entity
     * has to advertise PasswordAuthenticatedUserInterface for password login to work.
     */
    public function testItSatisfiesBothSecurityContracts(): void
    {
        $reflection = new \ReflectionClass(User::class);

        self::assertTrue($reflection->implementsInterface(BaseUserInterface::class));
        self::assertTrue($reflection->implementsInterface(PasswordAuthenticatedUserInterface::class));
        self::assertTrue($reflection->implementsInterface(UserInterface::class));
    }

    public function testTheEmailRoundTrips(): void
    {
        $user = new User();
        self::assertNull($user->getEmail());

        self::assertSame($user, $user->setEmail('user@example.com'));
        self::assertSame('user@example.com', $user->getEmail());
    }

    /**
     * getUsername() was dropped from UserInterface in Symfony 6.0.
     */
    public function testTheIdentifierIsTheEmail(): void
    {
        self::assertSame('user@example.com', new User()->setEmail('user@example.com')->getUserIdentifier());
    }

    public function testAUserWithoutEmailCannotBeIdentified(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('cannot be identified');

        new User()->getUserIdentifier();
    }

    public function testEveryUserIsAtLeastARoleUser(): void
    {
        self::assertSame(['ROLE_USER'], new User()->getRoles());
    }

    public function testRolesAreDeduplicated(): void
    {
        $user = new User()->setRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    /**
     * array_unique() keeps the original keys; Symfony expects a plain list of roles.
     */
    public function testRolesAreReturnedAsAList(): void
    {
        $roles = new User()->setRoles(['ROLE_ADMIN', 'ROLE_USER'])->getRoles();

        self::assertSame(array_keys($roles), range(0, \count($roles) - 1));
    }

    public function testThePasswordRoundTrips(): void
    {
        $user = new User();
        self::assertSame('', $user->getPassword());

        self::assertSame($user, $user->setPassword('hashed'));
        self::assertSame('hashed', $user->getPassword());
    }

    public function testEraseCredentialsIsANoOp(): void
    {
        $user = new User()->setEmail('user@example.com')->setPassword('hashed');

        $user->eraseCredentials();

        self::assertSame('hashed', $user->getPassword());
    }

    public function testTheIdComesFromTheCoreBundleTrait(): void
    {
        self::assertNull(new User()->getId());
    }

    public function testTheEntityIsMappedThroughAttributes(): void
    {
        $attributes = new \ReflectionClass(User::class)->getAttributes(ORM\Entity::class);

        self::assertCount(1, $attributes);
        self::assertSame(UserRepository::class, $attributes[0]->newInstance()->repositoryClass);
    }

    public function testTheEmailColumnIsUniqueAndBounded(): void
    {
        $column = $this->column('email');

        self::assertSame(Types::STRING, $column->type);
        self::assertSame(180, $column->length);
        self::assertTrue($column->unique);
    }

    public function testTheRolesColumnIsJson(): void
    {
        self::assertSame(Types::JSON, $this->column('roles')->type);
    }

    public function testThePasswordColumnIsAString(): void
    {
        self::assertSame(Types::STRING, $this->column('password')->type);
    }

    private function column(string $property): ORM\Column
    {
        $attributes = new \ReflectionProperty(User::class, $property)->getAttributes(ORM\Column::class);

        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance();
    }
}
