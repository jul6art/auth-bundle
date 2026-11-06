<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\State\PasswordResetProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/password/reset`: the token of the e-mailed link and the new password. Public by decision: the token
 * is the credential. It serves once, and ends every session of the account.
 */
#[ApiResource(
    shortName: 'PasswordReset',
    operations: [
        new Post(
            uriTemplate: '/password/reset',
            status: 204,
            security: "is_granted('PUBLIC_ACCESS')",
            output: false,
            processor: PasswordResetProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class PasswordReset
{
    #[Assert\NotBlank(message: 'error.password_reset.invalid_token')]
    public string $token = '';

    #[Assert\NotBlank(message: 'error.password.too_short')]
    #[Assert\Length(min: PasswordChange::MIN_LENGTH, max: 4096, minMessage: 'error.password.too_short', maxMessage: 'error.password.too_long')]
    public string $password = '';
}
