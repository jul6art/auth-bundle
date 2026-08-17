<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Manager;

use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Repository\Traits\UserRepositoryAwareTrait;
use Jul6Art\CoreBundle\Manager\AbstractManager;

/**
 * Class UserManager.
 *
 * @extends AbstractManager<User>
 */
class UserManager extends AbstractManager implements UserManagerInterface
{
    use UserRepositoryAwareTrait;
}
