<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * symfonycasts' repository of reset requests, one per realm: a reset link of one application never changes the
 * password of another's account. The realm is the request's: the forgotten-password and the reset routes are
 * reached on the application's own host. Set it as `symfonycasts_reset_password.request_password_repository`.
 */
final readonly class RealmResetPasswordRequests implements ResetPasswordRequestRepositoryInterface
{
    public function __construct(
        private Realms $realms,
        private ManagerRegistry $registry,
    ) {
    }

    #[\Override]
    public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        return $this->repository()->createResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }

    #[\Override]
    public function getUserIdentifier(object $user): string
    {
        return $this->repository()->getUserIdentifier($user);
    }

    #[\Override]
    public function persistResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->repository()->persistResetPasswordRequest($resetPasswordRequest);
    }

    #[\Override]
    public function findResetPasswordRequest(string $selector): ?ResetPasswordRequestInterface
    {
        return $this->repository()->findResetPasswordRequest($selector);
    }

    #[\Override]
    public function getMostRecentNonExpiredRequestDate(object $user): ?\DateTimeInterface
    {
        return $this->repository()->getMostRecentNonExpiredRequestDate($user);
    }

    #[\Override]
    public function removeResetPasswordRequest(ResetPasswordRequestInterface $resetPasswordRequest): void
    {
        $this->repository()->removeResetPasswordRequest($resetPasswordRequest);
    }

    #[\Override]
    public function removeExpiredResetPasswordRequests(): int
    {
        $removed = 0;
        foreach ($this->realms->all() as $realm) {
            $removed += $this->repositoryOf($realm->resetPasswordRequestClass)->removeExpiredResetPasswordRequests();
        }

        return $removed;
    }

    private function repository(): ResetPasswordRequestRepositoryInterface
    {
        $realm = $this->realms->current();
        if (null === $realm) {
            throw new \LogicException('A reset request belongs to a realm of the API sign-in: none answers this request.');
        }

        return $this->repositoryOf($realm->resetPasswordRequestClass);
    }

    /**
     * @param class-string $class
     */
    private function repositoryOf(string $class): ResetPasswordRequestRepositoryInterface
    {
        $repository = $this->registry->getManagerForClass($class)?->getRepository($class);
        if (!$repository instanceof ResetPasswordRequestRepositoryInterface) {
            throw new \LogicException(\sprintf('The repository of "%s" must implement %s.', $class, ResetPasswordRequestRepositoryInterface::class));
        }

        return $repository;
    }
}
