<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Repository\Interfaces;

use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\Repository\Interfaces\RepositoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Interface UserRepositoryInterface.
 *
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     *
     * PasswordUpgraderInterface narrowed this argument to
     * PasswordAuthenticatedUserInterface in Symfony 5.3.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;
}
