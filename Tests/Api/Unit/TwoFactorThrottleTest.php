<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Unit;

use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Jul6Art\AuthBundle\Api\Security\TwoFactorThrottle;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmB\Account as RealmBAccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(TwoFactorThrottle::class)]
final class TwoFactorThrottleTest extends TestCase
{
    public function testFiveWrongCodesSpendTheAccountsBudget(): void
    {
        $throttle = $this->throttle();
        $account = new Account('someone@example.test', 'Someone');

        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $throttle->failed($account);
            self::assertFalse($throttle->isExhausted($account));
        }
        $throttle->failed($account);

        self::assertTrue($throttle->isExhausted($account));
    }

    public function testASpentBudgetRefusesWithACodeNeverASentence(): void
    {
        $throttle = $this->throttle();
        $account = new Account('someone@example.test', 'Someone');
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $throttle->failed($account);
        }

        try {
            $throttle->guard($account);
            self::fail('A spent budget let the code be read.');
        } catch (AuthCodeException $exception) {
            self::assertSame([429, 'error.login.too_many_attempts'], [$exception->status, $exception->errorCode]);
        }
    }

    public function testARightCodeClearsTheBudget(): void
    {
        $throttle = $this->throttle();
        $account = new Account('someone@example.test', 'Someone');
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $throttle->failed($account);
        }

        $throttle->succeeded($account);
        $throttle->failed($account);

        self::assertFalse($throttle->isExhausted($account));
    }

    /**
     * Two realms may share an address, not a budget: a guesser in one application does not lock the account of
     * the other.
     */
    public function testEachRealmsAccountHasItsOwnBudget(): void
    {
        $throttle = $this->throttle();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $throttle->failed(new Account('someone@example.test', 'Someone'));
        }

        self::assertFalse($throttle->isExhausted(new RealmBAccount('someone@example.test', 'Someone')));
        self::assertFalse($throttle->isExhausted(new Account('someone-else@example.test', 'Someone')));
    }

    private function throttle(): TwoFactorThrottle
    {
        return new TwoFactorThrottle(
            new RateLimiterFactory(['id' => 'auth_two_factor', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '15 minutes'], new InMemoryStorage()),
            'a-secret',
        );
    }
}
