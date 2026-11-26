<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * An OpenID provider, as realm A's server reaches it in the tests: its discovery document, its keys, and its token
 * endpoint, which answers an identity token signed by a key of its own — with the claims the test asks for.
 */
final class OidcProviderDouble
{
    public const string ISSUER = 'https://console.test/api/oidc';

    public const string CLIENT = 'a.prod';

    public const string SECRET = 'a-secret-of-the-client';

    /** @var array<string, mixed> what the next identity token says, besides its issuer, audience and dates */
    public static array $claims = [];

    /** The token endpoint's status, when the test wants it to refuse. */
    public static int $tokenStatus = 200;

    /** Whether the next token is signed by another key than the one the provider publishes. */
    public static bool $foreignKey = false;

    /** @var list<array{method: string, url: string, body: string}> */
    public static array $requests = [];

    private static ?\OpenSSLAsymmetricKey $key = null;

    private static ?\OpenSSLAsymmetricKey $foreign = null;

    public static function client(): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            self::$requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($body) ? $body : ''];

            return match (true) {
                str_ends_with($url, '/.well-known/openid-configuration') => self::json(['issuer' => self::ISSUER, 'authorization_endpoint' => 'https://console.test/oidc', 'token_endpoint' => self::ISSUER.'/token', 'jwks_uri' => self::ISSUER.'/jwks']),
                str_ends_with($url, '/jwks') => self::json(['keys' => [self::jwk()]]),
                str_ends_with($url, '/token') => 200 === self::$tokenStatus ? self::json(['access_token' => 'an-access-token', 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => self::idToken()]) : new MockResponse('{"error":"invalid_grant"}', ['http_code' => self::$tokenStatus]),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });
    }

    /**
     * The provider changes its pair, under the same key name: what a realm kept of its keys no longer checks anything.
     */
    public static function rotate(): void
    {
        self::$key = self::generated();
    }

    public static function forget(): void
    {
        self::$claims = [];
        self::$tokenStatus = 200;
        self::$foreignKey = false;
        self::$requests = [];
    }

    private static function idToken(): string
    {
        $key = self::$foreignKey ? self::foreign() : self::key();
        openssl_pkey_export($key, $pem);
        $configuration = Configuration::forAsymmetricSigner(new Sha256(), InMemory::plainText(\is_string($pem) && '' !== $pem ? $pem : 'x'), InMemory::plainText(self::publicPem()));
        $now = new \DateTimeImmutable();
        $claims = self::$claims + ['iss' => self::ISSUER, 'aud' => self::CLIENT];
        $builder = $configuration->builder()
            ->issuedBy(\is_string($claims['iss']) && '' !== $claims['iss'] ? $claims['iss'] : self::ISSUER)
            ->permittedFor(\is_string($claims['aud']) && '' !== $claims['aud'] ? $claims['aud'] : self::CLIENT)
            ->relatedTo('42')
            ->issuedAt($now)
            ->expiresAt($now->modify('+5 minutes'))
            ->withHeader('kid', 'test-key');
        foreach ($claims as $name => $value) {
            if (!\in_array($name, ['iss', 'aud'], true) && '' !== $name) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }

    /**
     * @return array<string, string>
     */
    private static function jwk(): array
    {
        $details = openssl_pkey_get_details(self::key());
        $rsa = \is_array($details) && \is_array($details['rsa'] ?? null) ? $details['rsa'] : [];

        return ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key', 'n' => self::base64Url(\is_string($rsa['n'] ?? null) ? $rsa['n'] : ''), 'e' => self::base64Url(\is_string($rsa['e'] ?? null) ? $rsa['e'] : '')];
    }

    /**
     * @return non-empty-string
     */
    private static function publicPem(): string
    {
        $details = openssl_pkey_get_details(self::key());
        $pem = \is_array($details) && \is_string($details['key'] ?? null) ? $details['key'] : '';

        return '' === $pem ? 'x' : $pem;
    }

    private static function key(): \OpenSSLAsymmetricKey
    {
        return self::$key ??= self::generated();
    }

    private static function foreign(): \OpenSSLAsymmetricKey
    {
        return self::$foreign ??= self::generated();
    }

    private static function generated(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);

        return false === $key ? throw new \RuntimeException('No RSA key could be drawn.') : $key;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function json(array $document): MockResponse
    {
        return new MockResponse(json_encode($document, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
