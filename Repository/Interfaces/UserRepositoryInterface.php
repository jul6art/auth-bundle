<?php

namespace Jul6Art\AuthBundle\Repository\Interfaces;

use Jul6Art\CoreBundle\Repository\Interfaces\RepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Interface UserRepositoryInterface.
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void;
}
