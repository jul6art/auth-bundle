<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenManager;
use Gesdinet\JWTRefreshTokenBundle\Model\ExpiredRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\ListRefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * gesdinet's manager, one per realm: a refresh token lives in the base of its application and opens only its
 * application. Without it, a token issued on one host would be looked up — and found — on another, then
 * exchanged for an access token of an account that merely shares the address.
 *
 * It decorates gesdinet's manager from the inside (the hashing decorator wraps it); outside every realm — the
 * command line, the purge of expired tokens — it falls back on gesdinet's own.
 */
final class RealmRefreshTokens implements RefreshTokenManagerInterface, RevokeRefreshTokenManagerInterface, ListRefreshTokenManagerInterface, ExpiredRefreshTokenManagerInterface
{
    /** @var array<string, RefreshTokenManager> */
    private array $managers = [];

    /**
     * @param positive-int $batchSize gesdinet's `default_invalid_batch_size`
     */
    public function __construct(
        private readonly RefreshTokenManagerInterface&RevokeRefreshTokenManagerInterface&ListRefreshTokenManagerInterface&ExpiredRefreshTokenManagerInterface $inner,
        private readonly Realms $realms,
        private readonly ManagerRegistry $registry,
        private readonly int $batchSize,
    ) {
    }

    #[\Override]
    public function get(string $refreshToken): ?RefreshTokenInterface
    {
        return $this->current()->get($refreshToken);
    }

    #[\Override]
    public function getLastFromUsername(string $username): ?RefreshTokenInterface
    {
        return $this->current()->getLastFromUsername($username);
    }

    #[\Override]
    public function save(RefreshTokenInterface $refreshToken): void
    {
        $this->current()->save($refreshToken);
    }

    #[\Override]
    public function delete(RefreshTokenInterface $refreshToken, bool $andFlush = true): int
    {
        return $this->current()->delete($refreshToken, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalid(?\DateTimeInterface $datetime = null, bool $andFlush = true): array
    {
        return $this->current()->revokeAllInvalid($datetime, $andFlush);
    }

    #[\Override]
    public function revokeAllInvalidBatch(?\DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0, bool $andFlush = true): array
    {
        return $this->current()->revokeAllInvalidBatch($datetime, $batchSize, $offset, $andFlush);
    }

    #[\Override]
    public function getClass(): string
    {
        return $this->current()->getClass();
    }

    #[\Override]
    public function revokeAllExpired(?\DateTimeInterface $datetime = null): int
    {
        return $this->current()->revokeAllExpired($datetime);
    }

    #[\Override]
    public function revokeAllForUser(UserInterface $user): int
    {
        return $this->forAccount($user)->revokeAllForUser($user);
    }

    #[\Override]
    public function findAllForUser(UserInterface $user): array
    {
        return $this->forAccount($user)->findAllForUser($user);
    }

    #[\Override]
    public function revokeAllButNewestForUser(UserInterface $user, int $keep): int
    {
        return $this->forAccount($user)->revokeAllButNewestForUser($user, $keep);
    }

    private function current(): RefreshTokenManagerInterface&RevokeRefreshTokenManagerInterface&ListRefreshTokenManagerInterface&ExpiredRefreshTokenManagerInterface
    {
        $realm = $this->realms->current();

        return null === $realm ? $this->inner : $this->of($realm);
    }

    private function forAccount(UserInterface $account): RefreshTokenManagerInterface&RevokeRefreshTokenManagerInterface&ListRefreshTokenManagerInterface&ExpiredRefreshTokenManagerInterface
    {
        $realm = $this->realms->of($account);

        return null === $realm ? $this->inner : $this->of($realm);
    }

    private function of(Realm $realm): RefreshTokenManager
    {
        if (!isset($this->managers[$realm->name])) {
            $manager = $this->registry->getManagerForClass($realm->refreshTokenClass);
            if (null === $manager) {
                throw new \LogicException(\sprintf('No entity manager maps the refresh token "%s".', $realm->refreshTokenClass));
            }

            $this->managers[$realm->name] = new RefreshTokenManager($manager, $realm->refreshTokenClass, $this->batchSize);
        }

        return $this->managers[$realm->name];
    }
}
