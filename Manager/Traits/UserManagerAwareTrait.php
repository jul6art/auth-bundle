<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Manager\Traits;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait UserManagerAwareTrait.
 */
trait UserManagerAwareTrait
{
    protected UserManagerInterface $userManager;

    #[Required]
    public function setUserManager(UserManagerInterface $userManager): void
    {
        $this->userManager = $userManager;
    }
}
