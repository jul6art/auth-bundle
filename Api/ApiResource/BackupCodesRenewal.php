<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\Security\Constraint\CurrentTotpCode;
use Jul6Art\AuthBundle\Api\State\BackupCodesRenewalProcessor;

/**
 * `POST /api/me/backup-codes`: ten new backup codes, the previous ones stop working. An action on the second
 * factor itself, so it asks a current code of the app.
 */
#[ApiResource(
    shortName: 'BackupCodesRenewal',
    operations: [
        new Post(
            uriTemplate: '/me/backup-codes',
            status: 200,
            security: "is_granted('".AuthVoter::SELF."')",
            output: BackupCodes::class,
            processor: BackupCodesRenewalProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class BackupCodesRenewal
{
    #[CurrentTotpCode]
    public string $code = '';
}
