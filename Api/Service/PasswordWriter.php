<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Service;

use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Writes a new password — changed by the account, or reset through the e-mailed link — and ends every session
 * it had: a password that changes is often a password that leaked.
 */
final readonly class PasswordWriter
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private Accounts $accounts,
        private RevokeRefreshTokenManagerInterface $refreshTokens,
    ) {
    }

    public function write(ApiAccountInterface $account, string $plainPassword): void
    {
        $account->setPassword($this->hasher->hashPassword($account, $plainPassword));
        $this->accounts->save($account);
        $this->refreshTokens->revokeAllForUser($account);
    }
}
