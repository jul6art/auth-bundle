<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Unit;

use Jul6Art\AuthBundle\Api\Security\TwoFactorPolicy;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwoFactorPolicy::class)]
final class TwoFactorPolicyTest extends TestCase
{
    public function testProductionAlwaysDemandsIt(): void
    {
        self::assertTrue(new TwoFactorPolicy(false, 'prod')->required());
        self::assertTrue(new TwoFactorPolicy(false, 'prod')->appliesTo(new Account('a@example.test', 'A')));
    }

    public function testAWorkstationMaySwitchItOff(): void
    {
        $policy = new TwoFactorPolicy(false, 'dev');

        self::assertFalse($policy->required());
        self::assertFalse($policy->appliesTo(new Account('a@example.test', 'A')));
    }

    public function testSwitchingItOffNeverLowersTheGuardOfAnEnrolledAccount(): void
    {
        $account = new Account('a@example.test', 'A');
        $account->startTotpEnrolment('JBSWY3DPEHPK3PXP')->confirmTotp(new \DateTimeImmutable());

        self::assertTrue(new TwoFactorPolicy(false, 'dev')->appliesTo($account));
    }
}
