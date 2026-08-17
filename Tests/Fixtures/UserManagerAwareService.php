<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Fixtures;

use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Manager\Traits\UserManagerAwareTrait;

/**
 * Uses UserManagerAwareTrait, which the bundle ships for consumers, and exposes what
 * was injected so the trait is covered without reaching into protected state.
 */
class UserManagerAwareService
{
    use UserManagerAwareTrait;

    public function userManager(): UserManagerInterface
    {
        return $this->userManager;
    }
}
