<?php

namespace Jul6Art\AuthBundle\Repository\Traits;

use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;

/**
 * Trait UserRepositoryAwareTrait.
 */
trait UserRepositoryAwareTrait
{
    /**
     * @var UserRepositoryInterface
     */
    protected $userRepository;

    /**
     * @required
     */
    public function setUserRepository(UserRepositoryInterface $userRepository): void
    {
        $this->userRepository = $userRepository;
    }
}
