<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * The second factor of the sign-in (`POST /api/2fa_check`) spends the account's budget of wrong codes
 * ({@see TwoFactorThrottle}): once it is spent, the attempt is refused before the code is read, and
 * {@see TwoFactorFailureHandler} answers 429 `error.login.too_many_attempts`.
 *
 * Only for the accounts of an API realm: a website's second factor (admin-bundle) keeps its own
 * listener, and counting a failure twice would halve its budget.
 */
#[AsEventListener(event: TwoFactorAuthenticationEvents::ATTEMPT, method: 'onAttempt')]
#[AsEventListener(event: TwoFactorAuthenticationEvents::FAILURE, method: 'onFailure')]
#[AsEventListener(event: TwoFactorAuthenticationEvents::COMPLETE, method: 'onComplete')]
final readonly class TwoFactorThrottleListener
{
    public function __construct(
        private TwoFactorThrottle $throttle,
    ) {
    }

    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $account = $event->getToken()->getUser();
        if ($account instanceof ApiAccountInterface && $this->throttle->isExhausted($account)) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }

    public function onFailure(TwoFactorAuthenticationEvent $event): void
    {
        $account = $event->getToken()->getUser();
        if ($account instanceof ApiAccountInterface) {
            $this->throttle->failed($account);
        }
    }

    public function onComplete(TwoFactorAuthenticationEvent $event): void
    {
        $account = $event->getToken()->getUser();
        if ($account instanceof ApiAccountInterface) {
            $this->throttle->succeeded($account);
        }
    }
}
