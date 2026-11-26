<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

/**
 * The application as a client of an OpenID provider: the issuer it trusts, its identifier and its secret there, and —
 * when its server reaches the provider elsewhere than at the issuer's address, as in a network of containers — that
 * address.
 */
final readonly class OidcClient
{
    public function __construct(
        public string $issuer,
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        public ?string $backchannel = null,
    ) {
    }
}
