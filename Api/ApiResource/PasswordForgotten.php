<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\State\PasswordForgottenProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/password/forgot`: public by decision. It answers 202 whether the address has an account or not — a
 * different answer would tell a visitor who has an account.
 */
#[ApiResource(
    shortName: 'PasswordForgotten',
    operations: [
        new Post(
            uriTemplate: '/password/forgot',
            status: 202,
            security: "is_granted('PUBLIC_ACCESS')",
            output: false,
            processor: PasswordForgottenProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class PasswordForgotten
{
    #[Assert\NotBlank(message: 'error.email.invalid')]
    #[Assert\Email(message: 'error.email.invalid')]
    public string $email = '';
}
