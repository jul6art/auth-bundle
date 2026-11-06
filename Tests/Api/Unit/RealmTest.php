<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Unit;

use Jul6Art\AuthBundle\Api\Realm\Realm;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\LoginRecord;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\RefreshToken;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\ResetPasswordRequest;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Realm::class)]
final class RealmTest extends TestCase
{
    public function testTheResetLinkIsInTheAccountsLanguageOrTheFirstOne(): void
    {
        $realm = $this->realm(['fr' => 'https://a.test/mot-de-passe/{token}', 'en' => 'https://a.test/password/{token}']);

        self::assertSame('https://a.test/password/abc', $realm->resetLink('en', 'abc'));
        self::assertSame('https://a.test/mot-de-passe/abc', $realm->resetLink('fr', 'abc'));
        self::assertSame('https://a.test/mot-de-passe/abc', $realm->resetLink('de', 'abc'));
    }

    public function testTheTokenIsEncodedInTheLink(): void
    {
        self::assertSame('https://a.test/password/a%2Fb%3Fc', $this->realm(['en' => 'https://a.test/password/{token}'])->resetLink('en', 'a/b?c'));
    }

    public function testARealmOwnsOnlyItsAccountClass(): void
    {
        $realm = $this->realm(['en' => 'https://a.test/{token}']);

        self::assertTrue($realm->owns(new Account('a@example.test', 'A')));
        self::assertFalse($realm->owns(new RealmB\Account('a@example.test', 'A')));
    }

    /**
     * @param array<string, string> $links
     */
    private function realm(array $links): Realm
    {
        return new Realm('a', ['a_api'], Account::class, LoginRecord::class, RefreshToken::class, ResetPasswordRequest::class, 'Realm A', 'no-reply@a.test', $links, 'html', 'text', 'subject', 'domain');
    }
}
