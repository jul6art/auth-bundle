<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The budget of wrong codes of an ACCOUNT: five per quarter of an hour by default
 * (`auth.api.two_factor_throttling`), then even the right code is refused before it is read.
 *
 * ⚠️ **Six digits fall in a million attempts, and the firewall's `login_throttling` does not stop
 * that.** It counts per (identifier, IP) over a minute: a client that changes its address, or simply
 * waits, gets five more. This budget is the account's, whatever the address, and every door that
 * checks a code spends it — the second factor of the sign-in ({@see TwoFactorThrottleListener}), the
 * enrolment, the activation, and the `#[CurrentTotpCode]` of the account's settings.
 *
 * A right code clears it. The keys are hashed with the application's secret, and carry the account's
 * class: two realms may share an address, not a budget.
 */
final readonly class TwoFactorThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $limiter,
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    public function isExhausted(UserInterface $account): bool
    {
        return 0 === $this->limiter($account)->consume(0)->getRemainingTokens();
    }

    /**
     * Refuses, before the code is read, an account that has spent its budget: a 429 with the code the
     * sign-in's front already translates.
     *
     * @throws AuthCodeException
     */
    public function guard(UserInterface $account): void
    {
        if ($this->isExhausted($account)) {
            throw new AuthCodeException(Response::HTTP_TOO_MANY_REQUESTS, 'error.login.too_many_attempts');
        }
    }

    public function failed(UserInterface $account): void
    {
        $this->limiter($account)->consume();
    }

    public function succeeded(UserInterface $account): void
    {
        $this->limiter($account)->reset();
    }

    private function limiter(UserInterface $account): LimiterInterface
    {
        return $this->limiter->create(hash_hmac('sha256', $account::class."\0".$account->getUserIdentifier(), $this->secret));
    }
}
