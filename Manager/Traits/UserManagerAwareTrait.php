<?php

namespace Jul6Art\AuthBundle\Manager\Traits;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;

/**
 * Trait UserManagerAwareTrait.
 */
trait UserManagerAwareTrait
{
    /**
     * @var UserManagerInterface
     */
    protected $userManager;

    /**
     * @required
     */
    public function setUserManager(UserManagerInterface $userManager): void
    {
        $this->userManager = $userManager;
    }
}
