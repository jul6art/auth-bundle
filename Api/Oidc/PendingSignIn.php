<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Jul6Art\AuthBundle\Api\Realm\Realm;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sign-in sent to the provider and not back yet — its state, its nonce, its PKCE verifier and the "stay signed in"
 * box —, kept in a cookie of its own: signed with the kernel's secret, ten minutes, `SameSite=Lax`, `__Host-` so that
 * no other host of the domain sets it.
 *
 * Not in the sign-in session: its cookie is `SameSite=Strict`, and a browser does not send it back when a provider on
 * another site sends it back — the sign-in would always be refused.
 */
final readonly class PendingSignIn
{
    public const string COOKIE = '__Host-auth_oidc';

    private const int TTL = 600;

    private string $key;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        #[\SensitiveParameter]
        string $secret,
        private ClockInterface $clock,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, 'jul6art/auth-bundle: a pending sign-in through an OpenID provider');
    }

    /**
     * The cookie of a sign-in just started.
     */
    public function cookie(Realm $realm, string $state, string $nonce, #[\SensitiveParameter] string $verifier, bool $remember): Cookie
    {
        $expires = $this->clock->now()->getTimestamp() + self::TTL;
        $payload = self::base64Url((string) json_encode(['r' => $realm->name, 's' => $state, 'n' => $nonce, 'v' => $verifier, 'm' => $remember, 'e' => $expires], \JSON_THROW_ON_ERROR));

        return Cookie::create(self::COOKIE, $payload.'.'.$this->signature($payload), $expires, '/', null, true, true, false, Cookie::SAMESITE_LAX);
    }

    /**
     * The sign-in this browser started in this realm, if its cookie is there, intact and not expired.
     *
     * @return array{state: string, nonce: string, verifier: string, remember: bool}|null
     */
    public function of(Request $request, Realm $realm): ?array
    {
        $parts = explode('.', $request->cookies->getString(self::COOKIE), 2);
        if (2 !== \count($parts) || !hash_equals($this->signature($parts[0]), $parts[1])) {
            return null;
        }
        $pending = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!\is_array($pending) || $realm->name !== ($pending['r'] ?? null) || !\is_int($pending['e'] ?? null) || $pending['e'] < $this->clock->now()->getTimestamp()) {
            return null;
        }
        foreach (['s', 'n', 'v'] as $key) {
            if (!\is_string($pending[$key] ?? null) || '' === $pending[$key]) {
                return null;
            }
        }

        return ['state' => $pending['s'], 'nonce' => $pending['n'], 'verifier' => $pending['v'], 'remember' => false !== ($pending['m'] ?? true)];
    }

    /**
     * Its cookie removed: a sign-in comes back once.
     */
    public function forget(Response $response): Response
    {
        $response->headers->clearCookie(self::COOKIE, '/', null, true, true, Cookie::SAMESITE_LAX);

        return $response;
    }

    private function signature(string $payload): string
    {
        return self::base64Url(hash_hmac('sha256', $payload, $this->key, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
