<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Functional;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Manager\UserManager;
use Jul6Art\AuthBundle\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Runs the repository and the manager against a real in-memory SQLite database.
 * This is what proves the ORM 3 port: EntityRepository dropped the $_em property
 * UserRepository::upgradePassword() used to rely on, and the User mapping moved
 * from annotations to attributes.
 */
#[CoversNothing]
final class OrmIntegrationTest extends AbstractFunctionalTestCase
{
    private const string TABLE = 'User';

    private ContainerInterface $container;
    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->boot();

        $entityManager = $this->container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(User::class),
        ]);
    }

    /**
     * The table is named "User": Doctrine's default naming strategy uses the class
     * short name as is, which the annotation mapping did too.
     */
    public function testTheUserTableIsCreatedFromTheAttributes(): void
    {
        $columns = $this->entityManager->getConnection()
            ->createSchemaManager()
            ->listTableColumns(self::TABLE);

        $names = array_map(static fn (Column $column): string => $column->getName(), $columns);
        sort($names);

        self::assertSame(['email', 'id', 'password', 'roles'], $names);
    }

    public function testTheIdIsAutoIncremented(): void
    {
        $user = new User()->setEmail('first@example.com')->setPassword('hashed');
        self::assertNull($user->getId());

        $this->repository()->save($user);

        self::assertSame(1, $user->getId());
    }

    public function testSaveAndFindRoundTripThroughTheRepository(): void
    {
        $user = new User()->setEmail('round@example.com')->setPassword('hashed')->setRoles(['ROLE_ADMIN']);
        $this->repository()->save($user);

        $this->entityManager->clear();

        $found = $this->repository()->find($user->getId());

        self::assertInstanceOf(User::class, $found);
        self::assertSame('round@example.com', $found->getEmail());
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $found->getRoles());
    }

    /**
     * upgradePassword() used $this->_em, removed from EntityRepository in ORM 3.
     */
    public function testUpgradePasswordPersistsTheNewHash(): void
    {
        $user = new User()->setEmail('upgrade@example.com')->setPassword('old-hash');
        $this->repository()->save($user);

        $this->repository()->upgradePassword($user, 'new-hash');

        $this->entityManager->clear();

        $found = $this->repository()->find($user->getId());
        self::assertInstanceOf(User::class, $found);
        self::assertSame('new-hash', $found->getPassword());
    }

    public function testUpgradePasswordRejectsForeignUsers(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessageIsOrContains(InMemoryUser::class);

        $this->repository()->upgradePassword(new InMemoryUser('bob', 'hash'), 'new-hash');
    }

    public function testDeleteRemovesTheRow(): void
    {
        $user = new User()->setEmail('doomed@example.com')->setPassword('hashed');
        $repository = $this->repository();

        $repository->save($user);
        self::assertSame(1, $this->countRows());

        $repository->delete($user);
        self::assertSame(0, $this->countRows());
    }

    /**
     * CoreBundle's AbstractManager resolves "$userRepository" by reflection from the
     * "UserManager" class name; the trait is what provides that property.
     */
    public function testTheManagerDelegatesToTheWiredRepository(): void
    {
        $manager = $this->manager();

        $user = new User()->setEmail('managed@example.com')->setPassword('hashed');
        $manager->save($user);

        self::assertSame(1, $this->countRows());
        self::assertNotNull($user->getId());

        $found = $manager->getById($user->getId());
        self::assertInstanceOf(User::class, $found);
        self::assertSame('managed@example.com', $found->getEmail());

        self::assertCount(1, [...$manager->getAll()]);

        $manager->delete($user);
        self::assertSame(0, $this->countRows());
    }

    public function testTheManagerResolvesTheContainerWiredRepository(): void
    {
        $manager = $this->manager();

        self::assertSame(
            $this->container->get(UserRepository::class),
            new \ReflectionMethod($manager, 'getAbstractRepository')->invoke($manager)
        );
    }

    private function repository(): UserRepository
    {
        $repository = $this->container->get(UserRepository::class);

        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function manager(): UserManager
    {
        $manager = $this->container->get(UserManager::class);

        self::assertInstanceOf(UserManager::class, $manager);

        return $manager;
    }

    /**
     * Counts through raw SQL so the ORM identity map cannot mask a missing write.
     */
    private function countRows(): int
    {
        $count = $this->entityManager->getConnection()
            ->executeQuery(\sprintf('SELECT COUNT(*) FROM "%s"', self::TABLE))
            ->fetchOne();

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
