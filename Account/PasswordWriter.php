<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account;

use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Writes a new password — changed by the account, or reset through the e-mailed link, on the API as on the web —
 * and ends every API session it had: a password that changes is often a password that leaked.
 *
 * One implementation for both doors (ADR-0001 of admin-bundle, T20 lot 3): the web screens of admin-bundle call it
 * too. The refresh tokens are revoked when gesdinet's manager is there — an application without the API sign-in
 * has no session to end beyond the one the firewall holds.
 */
final readonly class PasswordWriter
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private ManagerRegistry $registry,
        private ?RevokeRefreshTokenManagerInterface $refreshTokens = null,
    ) {
    }

    public function write(PasswordAuthenticatedUserInterface $account, string $plainPassword): void
    {
        if (!method_exists($account, 'setPassword')) {
            throw new \LogicException(\sprintf('%s has no setPassword(): its password cannot be written.', $account::class));
        }

        $account->setPassword($this->hasher->hashPassword($account, $plainPassword));

        $manager = $this->registry->getManagerForClass($account::class)
            ?? throw new \LogicException(\sprintf('No entity manager maps "%s".', $account::class));
        $manager->persist($account);
        $manager->flush();

        if ($account instanceof UserInterface) {
            $this->refreshTokens?->revokeAllForUser($account);
        }
    }
}
