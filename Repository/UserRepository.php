<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Jul6Art\CoreBundle\Repository\AbstractRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Class UserRepository.
 *
 * The find* return types now come from the generic AbstractRepository rather than
 * from the hand written docblock tags this class used to carry.
 *
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository implements PasswordUpgraderInterface, UserRepositoryInterface
{
    /**
     * @param class-string<User> $userClass
     */
    public function __construct(ManagerRegistry $registry, string $userClass = User::class)
    {
        parent::__construct($registry, $userClass);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     *
     * @throws UnsupportedUserException if the user is not one of ours
     */
    #[\Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
