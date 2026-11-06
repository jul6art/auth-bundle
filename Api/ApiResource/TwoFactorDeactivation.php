<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\Security\Constraint\CurrentTotpCode;
use Jul6Art\AuthBundle\Api\State\TwoFactorDeactivationProcessor;

/**
 * `POST /api/me/two-factor/deactivation`: a current code of the app switches the second factor off, its backup
 * codes with it — only where the sign-in does not demand it. In production it always does: the answer is then a
 * 403. The front reads the account again afterwards.
 */
#[ApiResource(
    shortName: 'TwoFactorDeactivation',
    operations: [
        new Post(
            uriTemplate: '/me/two-factor/deactivation',
            status: 204,
            security: "is_granted('".AuthVoter::SELF."')",
            output: false,
            processor: TwoFactorDeactivationProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class TwoFactorDeactivation
{
    #[CurrentTotpCode]
    public string $code = '';
}
