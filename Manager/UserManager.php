<?php

namespace Jul6Art\AuthBundle\Manager;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Repository\Traits\UserRepositoryAwareTrait;
use Jul6Art\CoreBundle\Manager\AbstractManager;

/**
 * Class AbstractManager.
 */
class UserManager extends AbstractManager implements UserManagerInterface
{
    use UserRepositoryAwareTrait;
}
