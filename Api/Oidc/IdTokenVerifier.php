<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Jul6Art\AuthBundle\Api\Assistance\Assistances;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Psr\Clock\ClockInterface;

/**
 * The provider's identity token, checked before anything is trusted of it: signed RS256 by one of the provider's keys,
 * issued by the issuer the realm trusts, for this client, valid now (thirty seconds of leeway), with the nonce of this
 * sign-in, and the factors the realm asks for. It says who signed in: an address the provider verified.
 */
final readonly class IdTokenVerifier
{
    public function __construct(
        private OidcProvider $provider,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $methods the factors the provider must have checked
     *
     * @return array{email: string, subject: string, actor: array{email: string, name: string}|null} `actor`: whoever acts
     *                                                                                               for the account, when
     *                                                                                               the provider names one
     *                                                                                               (`act`, RFC 8693)
     */
    public function verify(OidcClient $client, #[\SensitiveParameter] string $idToken, string $nonce, array $methods): array
    {
        try {
            $token = new Parser(new JoseEncoder())->parse('' === $idToken ? '.' : $idToken);
        } catch (InvalidTokenStructure|\InvalidArgumentException $exception) {
            throw new OidcRefusal('auth.oidc.invalid', 'an identity token that does not read', $exception);
        }
        if (!$token instanceof UnencryptedToken || 'RS256' !== $token->headers()->get('alg')) {
            throw new OidcRefusal('auth.oidc.invalid', 'an identity token not signed RS256');
        }
        $issuer = '' === $client->issuer ? throw new OidcRefusal('auth.oidc.invalid', 'no issuer') : $client->issuer;
        $audience = '' === $client->clientId ? throw new OidcRefusal('auth.oidc.invalid', 'no client') : $client->clientId;
        $kid = $token->headers()->get('kid');
        $holds = fn (bool $fresh): bool => new Validator()->validate(
            $token,
            new SignedWith(new Sha256(), InMemory::plainText($this->key($client, $kid, $fresh))),
            new IssuedBy($issuer),
            new PermittedFor($audience),
            new LooseValidAt($this->clock, new \DateInterval('PT30S')),
        );
        // The keys kept an hour, then the provider's own once more: it may have changed its pair under the same name.
        $valid = $holds(false) || $holds(true);
        $claims = $token->claims();
        // OpenID Connect asks `exp` and `iat` of an identity token, never `nbf`: the loose check, and these two required.
        if (!$valid || !$claims->has('exp') || !$claims->has('iat') || !\is_string($claims->get('nonce')) || !hash_equals($nonce, $claims->get('nonce'))) {
            throw new OidcRefusal('auth.oidc.invalid', 'an identity token that does not hold');
        }
        $amr = $claims->get('amr');
        foreach ($methods as $method) {
            if (!\is_array($amr) || !\in_array($method, $amr, true)) {
                throw new OidcRefusal('auth.oidc.factor', $method);
            }
        }
        $email = $claims->get('email');
        $subject = $claims->get('sub');
        if (!\is_string($email) || '' === $email || true !== $claims->get('email_verified') || !\is_string($subject)) {
            throw new OidcRefusal('auth.oidc.invalid', 'no verified address');
        }

        $act = $claims->get(Assistances::CLAIM);
        $actor = null;
        if (null !== $act) {
            if (!\is_array($act) || !\is_string($act['email'] ?? null) || '' === $act['email']) {
                throw new OidcRefusal('auth.oidc.invalid', 'an actor without an address');
            }
            $actor = ['email' => $act['email'], 'name' => \is_string($act['name'] ?? null) && '' !== $act['name'] ? $act['name'] : $act['email']];
        }

        return ['email' => $email, 'subject' => $subject, 'actor' => $actor];
    }

    /**
     * The PEM of the key the token names — asked of the provider again when it publishes none of that name.
     *
     * @return non-empty-string
     */
    private function key(OidcClient $client, mixed $kid, bool $fresh): string
    {
        foreach ($fresh ? [true] : [false, true] as $again) {
            foreach ($this->provider->keys($client, $again) as $jwk) {
                if (($jwk['kid'] ?? null) === $kid && 'RSA' === ($jwk['kty'] ?? null) && \is_string($jwk['n'] ?? null) && \is_string($jwk['e'] ?? null)) {
                    return Jwk::pem($jwk['n'], $jwk['e']);
                }
            }
        }

        throw new OidcRefusal('auth.oidc.invalid', 'a key the provider does not publish');
    }
}
