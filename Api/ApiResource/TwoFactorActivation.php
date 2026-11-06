<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\State\TwoFactorActivationProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/me/two-factor`: the first code of the app switches the second factor on, and the ten backup codes
 * come back — shown once.
 */
#[ApiResource(
    shortName: 'TwoFactorActivation',
    operations: [
        new Post(
            uriTemplate: '/me/two-factor',
            status: 200,
            security: "is_granted('".AuthVoter::SELF."')",
            output: BackupCodes::class,
            processor: TwoFactorActivationProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class TwoFactorActivation
{
    #[Assert\NotBlank(message: 'error.two_factor.code_missing')]
    public string $code = '';
}
