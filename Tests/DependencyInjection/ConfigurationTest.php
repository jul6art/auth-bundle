<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\DependencyInjection;

use Jul6Art\AuthBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsAuth(): void
    {
        self::assertSame('auth', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    public function testItIsEnabledByDefault(): void
    {
        self::assertTrue($this->process([])['enabled']);
    }

    public function testItCanBeDisabled(): void
    {
        self::assertFalse($this->process([['enabled' => false]])['enabled']);
    }

    /**
     * The API sign-in stays off unless an application asks for it: its packages are only suggested.
     */
    public function testTheApiSignInIsOffByDefault(): void
    {
        self::assertSame(['enabled' => false, 'two_factor_required' => true, 'realms' => []], $this->process([])['api']);
    }

    public function testARealmTakesTheBundlesTemplatesUnlessItNamesItsOwn(): void
    {
        $api = $this->process([['api' => ['realms' => ['shop' => [
            'firewalls' => ['shop_login', 'shop_api'],
            'user_class' => 'App\Entity\User',
            'login_record_class' => 'App\Entity\LoginRecord',
            'refresh_token_class' => 'App\Entity\RefreshToken',
            'reset_password_request_class' => 'App\Entity\ResetPasswordRequest',
            'issuer' => 'Shop',
            'mail' => ['from' => 'no-reply@example.org', 'reset_links' => ['en' => 'https://example.org/password/{token}']],
        ]]]]])['api'];

        self::assertIsArray($api);
        self::assertTrue($api['enabled']);
        $realms = $api['realms'];
        self::assertIsArray($realms);
        $shop = $realms['shop'];
        self::assertIsArray($shop);
        self::assertSame([
            'from' => 'no-reply@example.org',
            'reset_links' => ['en' => 'https://example.org/password/{token}'],
            'html_template' => '@Auth/emails/password_reset.html.twig',
            'text_template' => '@Auth/emails/password_reset.txt.twig',
            'subject' => 'password_reset.subject',
            'translation_domain' => 'auth_emails',
        ], $shop['mail'] ?? null);
    }

    public function testARealmWithoutAResetLinkIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['api' => ['realms' => ['shop' => [
            'firewalls' => ['shop_api'],
            'user_class' => 'App\Entity\User',
            'login_record_class' => 'App\Entity\LoginRecord',
            'refresh_token_class' => 'App\Entity\RefreshToken',
            'reset_password_request_class' => 'App\Entity\ResetPasswordRequest',
            'issuer' => 'Shop',
            'mail' => ['from' => 'no-reply@example.org', 'reset_links' => []],
        ]]]]]);
    }

    /**
     * enabled is a booleanNode, so it no longer silently accepts arbitrary scalars.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanValues(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['enabled' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [0];
        yield 'array' => [[]];
    }

    public function testTheSignInLimiterIsOffByDefault(): void
    {
        self::assertSame(
            ['enabled' => false, 'paths' => [], 'max_attempts' => 5, 'interval' => '1 minute', 'cache_pool' => null],
            $this->process([])['sign_in_throttling'],
        );
    }

    /**
     * A limiter without a path counts nothing, and would look like protection.
     */
    public function testTheSignInLimiterRefusesToCountNothing(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('needs at least one path');

        $this->process([['sign_in_throttling' => ['enabled' => true]]]);
    }

    public function testTheSignInLimiterTakesItsPathsAndBudget(): void
    {
        $config = $this->process([['sign_in_throttling' => ['paths' => ['/api/auth/', '/login'], 'max_attempts' => 3, 'cache_pool' => 'cache.limiter']]])['sign_in_throttling'];
        self::assertIsArray($config);

        self::assertTrue($config['enabled']);
        self::assertSame(['/api/auth/', '/login'], $config['paths']);
        self::assertSame(3, $config['max_attempts']);
        self::assertSame('cache.limiter', $config['cache_pool']);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
