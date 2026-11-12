<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Test;

use Jul6Art\AuthBundle\Test\FirewallRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FirewallRules::class)]
final class FirewallRulesTest extends TestCase
{
    private const array SECURE = [
        'firewalls' => [
            'dev' => ['pattern' => '^/_profiler', 'security' => false],
            'api' => ['stateless' => true, 'jwt' => null, 'user_checker' => 'App\\Security\\Checker'],
            'main' => [
                'form_login' => ['login_path' => 'login'],
                'login_throttling' => ['max_attempts' => 5],
                'user_checker' => 'App\\Security\\Checker',
                'logout' => ['path' => 'logout'],
            ],
        ],
    ];

    private const array REFRESH = ['single_use' => true, 'hash_tokens' => ['enabled' => true]];

    public function testASecureConfigurationPasses(): void
    {
        self::assertSame([], FirewallRules::violations(self::SECURE, self::REFRESH, self::postOnly(...)));
        self::assertSame([], FirewallRules::violations(self::SECURE, null, self::postOnly(...)), 'Without gesdinet, no refresh-token rule.');
    }

    /**
     * @param array<string, mixed>      $security
     * @param array<string, mixed>|null $refresh
     * @param non-empty-string          $named
     */
    #[DataProvider('weakConfigurations')]
    public function testEachWeaknessIsNamed(array $security, ?array $refresh, string $named): void
    {
        $violations = FirewallRules::violations($security, $refresh, self::postOnly(...));

        self::assertNotSame([], $violations);
        self::assertStringContainsString($named, $violations[0]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>|null, non-empty-string}>
     */
    public static function weakConfigurations(): iterable
    {
        $unthrottled = self::SECURE;
        unset($unthrottled['firewalls']['main']['login_throttling']);
        yield 'a form login without throttling' => [$unthrottled, null, 'without login_throttling'];

        $customLogin = self::SECURE;
        $customLogin['firewalls']['main'] = ['custom_authenticator' => 'App\\Security\\Login', 'user_checker' => 'App\\Security\\Checker'];
        yield 'a custom authenticator without throttling' => [$customLogin, null, 'custom_authenticator'];

        $uncheckedApi = self::SECURE;
        unset($uncheckedApi['firewalls']['api']['user_checker']);
        yield 'a firewall without the account checker' => [$uncheckedApi, null, 'Firewall "api" uses user checker none'];

        $otherChecker = self::SECURE;
        $otherChecker['firewalls']['api']['user_checker'] = 'App\\Security\\Other';
        yield 'two checkers for the same accounts' => [$otherChecker, null, 'where another firewall of the same accounts uses'];

        yield 'refresh tokens reusable' => [self::SECURE, ['single_use' => false] + self::REFRESH, 'single_use'];
        yield 'refresh tokens in clear' => [self::SECURE, ['single_use' => true], 'hash_tokens'];

        $getLogout = self::SECURE;
        $getLogout['firewalls']['main']['logout'] = ['path' => 'logout_get'];
        yield 'a sign-out answering GET' => [$getLogout, null, 'answers GET'];

        $routeless = self::SECURE;
        $routeless['firewalls']['main']['logout'] = null;
        yield 'a sign-out without a route' => [$routeless, null, 'which no route declares'];
    }

    public function testAnotherProviderAnswersForItsOwnChecker(): void
    {
        $hosted = self::SECURE;
        $hosted['firewalls']['hosted_app'] = ['provider' => 'hosted_accounts', 'jwt' => null];

        self::assertSame([], FirewallRules::violations($hosted, null, self::postOnly(...)));
    }

    public function testATokenFirewallIsExemptFromThrottlingOnly(): void
    {
        $token = self::SECURE;
        $token['firewalls']['agent'] = ['stateless' => true, 'custom_authenticators' => ['App\\Security\\AgentAuthenticator'], 'user_checker' => 'App\\Security\\Checker'];

        self::assertNotSame([], FirewallRules::violations($token, null, self::postOnly(...)), 'Unnamed, it is a sign-in like any other.');
        self::assertSame([], FirewallRules::violations($token, null, self::postOnly(...), ['agent']));

        unset($token['firewalls']['agent']['user_checker']);
        $violations = FirewallRules::violations($token, null, self::postOnly(...), ['agent']);
        self::assertCount(1, $violations, 'Named, it still needs the account checker.');
        self::assertStringContainsString('Firewall "agent" uses user checker none', $violations[0]);
    }

    /**
     * Routes: `logout` answers POST, `logout_get` any method but also GET, `/logout` exists nowhere.
     *
     * @return list<string>|null
     */
    private static function postOnly(string $path): ?array
    {
        return match ($path) {
            'logout' => ['POST'],
            'logout_get' => ['GET', 'POST'],
            default => null,
        };
    }
}
