<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\State\TwoFactorSetupProvider;

/**
 * `GET /api/me/two-factor/setup`: the QR code and the key of a second factor the account sets up from its own
 * page — where the sign-in does not demand one. The secret is drawn on the first call only, and shown only while
 * the second factor is not active.
 */
#[ApiResource(
    shortName: 'TwoFactorSetup',
    operations: [
        new Get(
            uriTemplate: '/me/two-factor/setup',
            security: "is_granted('".AuthVoter::SELF."')",
            provider: TwoFactorSetupProvider::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final readonly class TwoFactorSetup
{
    /**
     * @param string $qrCode a `data:` URI (SVG)
     * @param string $secret the key by groups of four, for whoever types it
     */
    public function __construct(
        public string $qrCode,
        public string $secret,
    ) {
    }
}
