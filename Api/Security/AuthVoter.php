<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The access decisions of the sign-in's own routes.
 *
 * - `AUTH_SELF`: an active account, fully signed in — its second factor passed where it applies — acts on itself.
 * - `AUTH_ENROL`: an account in its sign-in session, whose second factor is not set up yet, enrols.
 *
 * @extends Voter<string, mixed>
 */
final class AuthVoter extends Voter
{
    public const string SELF = 'AUTH_SELF';

    public const string ENROL = 'AUTH_ENROL';

    public function __construct(
        private readonly TwoFactorPolicy $policy,
    ) {
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::SELF === $attribute || self::ENROL === $attribute;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $account = $token->getUser();
        if (!$account instanceof ApiAccountInterface || !$account->isActive() || $token instanceof TwoFactorTokenInterface) {
            return false;
        }

        return match ($attribute) {
            self::SELF => $account->isTotpAuthenticationEnabled() || !$this->policy->required(),
            default => !$account->isTotpAuthenticationEnabled(),
        };
    }
}
