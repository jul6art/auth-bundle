<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\State\PasswordChangeProcessor;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/me/password`: the current password, then the new one. A new password ends every other session of
 * the account — its refresh tokens are revoked.
 */
#[ApiResource(
    shortName: 'PasswordChange',
    operations: [
        new Post(
            uriTemplate: '/me/password',
            status: 204,
            security: "is_granted('".AuthVoter::SELF."')",
            output: false,
            processor: PasswordChangeProcessor::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class PasswordChange
{
    /** A passphrase rather than a password: length beats complexity rules. */
    public const int MIN_LENGTH = 12;

    #[Assert\NotBlank(message: 'error.password.current_invalid')]
    #[UserPassword(message: 'error.password.current_invalid')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'error.password.too_short')]
    #[Assert\Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'error.password.too_short', maxMessage: 'error.password.too_long')]
    public string $newPassword = '';
}
