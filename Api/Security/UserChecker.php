<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A deactivated account signs in nowhere, and its tokens stop working at the next request.
 *
 * ⚠️ It reads exactly like a wrong password: telling "deactivated" apart would tell a visitor the address has
 * an account.
 */
final class UserChecker implements UserCheckerInterface
{
    #[\Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof ApiAccountInterface && !$user->isActive()) {
            throw new BadCredentialsException('Deactivated account.');
        }
    }

    #[\Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
