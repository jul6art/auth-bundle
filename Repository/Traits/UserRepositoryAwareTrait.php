<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Repository\Traits;

use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait UserRepositoryAwareTrait.
 */
trait UserRepositoryAwareTrait
{
    /**
     * The property name is what CoreBundle's AbstractManager resolves by reflection
     * for a "UserManager", so it must stay "$userRepository".
     */
    protected UserRepositoryInterface $userRepository;

    #[Required]
    public function setUserRepository(UserRepositoryInterface $userRepository): void
    {
        $this->userRepository = $userRepository;
    }
}
