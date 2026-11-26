<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

/**
 * A realm's sign-in through an OpenID provider, as configured (`auth.api.realms.<name>.oidc`): its client, unless an
 * {@see OidcClientResolver} tells it per request; where the browser lands; what the provider must have checked.
 */
final readonly class OidcSettings
{
    /**
     * @param list<string> $methods the factors the provider must have checked, as its `amr` claim names them
     */
    public function __construct(
        public ?string $issuer,
        public ?string $clientId,
        #[\SensitiveParameter]
        public ?string $clientSecret,
        public ?string $backchannel,
        public string $afterSignIn,
        public string $signInPage,
        public array $methods,
    ) {
    }

    /**
     * The client configured, if complete.
     */
    public function client(): ?OidcClient
    {
        return null === $this->issuer || '' === $this->issuer || null === $this->clientId || '' === $this->clientId || null === $this->clientSecret || '' === $this->clientSecret
            ? null
            : new OidcClient($this->issuer, $this->clientId, $this->clientSecret, $this->backchannel);
    }
}
