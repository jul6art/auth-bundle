<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Unit;

use Jul6Art\AuthBundle\Api\Security\BackupCodes;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackupCodes::class)]
final class BackupCodesTest extends TestCase
{
    public function testTenDistinctCodesWithoutTheCharactersAPersonMisreads(): void
    {
        $codes = BackupCodes::generate();

        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $code);
        }
    }

    public function testACodeServesOnceWhateverItsCaseAndDash(): void
    {
        $account = new Account('a@example.test', 'A');
        $account->replaceBackupCodes(['ABCDE-FGHJK']);

        self::assertTrue($account->isBackupCode('abcde fghjk'));
        $account->invalidateBackupCode('ABCDEFGHJK');
        self::assertFalse($account->isBackupCode('ABCDE-FGHJK'));
        self::assertSame(0, $account->countBackupCodes());
    }
}
