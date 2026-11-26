<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

/**
 * A sign-in through the provider refused, in a code the sign-in page translates: `auth.oidc.denied` (the provider said
 * no), `auth.oidc.invalid` (an answer that does not hold), `auth.oidc.no_account` (no account of that address here),
 * `auth.oidc.inactive`, `auth.oidc.factor` (the provider did not check the factors the realm asks for),
 * `auth.oidc.unavailable` (the provider did not answer).
 */
final class OidcRefusal extends \RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $detail = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct('' === $detail ? $error : $error.': '.$detail, 0, $previous);
    }
}
