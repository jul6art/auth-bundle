<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether the second factor is mandatory: always in production, and switchable elsewhere
 * (`auth.api.two_factor_required`) — a workstation where the password alone signs in and nobody enrols. The
 * `prod` environment ignores the switch: a stray `false` on a server never opens it.
 *
 * Off, an account that HAS enrolled still owes its code: switching the policy off never lowers the guard of an
 * account that raised it.
 */
final readonly class TwoFactorPolicy implements TwoFactorConditionInterface
{
    private bool $required;

    public function __construct(
        #[Autowire(param: 'auth.api.two_factor_required')]
        bool $required,
        #[Autowire(param: 'kernel.environment')]
        string $environment,
    ) {
        $this->required = $required || 'prod' === $environment;
    }

    public function required(): bool
    {
        return $this->required;
    }

    /**
     * Whether this account must pass a second factor to sign in, or to confirm a sensitive action.
     */
    public function appliesTo(ApiAccountInterface $account): bool
    {
        return $this->required || $account->isTotpAuthenticationEnabled();
    }

    /**
     * scheb's condition: the second factor of the sign-in.
     */
    #[\Override]
    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        $account = $context->getUser();

        return !$account instanceof ApiAccountInterface || $this->appliesTo($account);
    }
}
