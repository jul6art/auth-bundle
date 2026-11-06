<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Api\ApiResource\TwoFactorDeactivation;
use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Jul6Art\AuthBundle\Api\Security\TwoFactorPolicy;
use Jul6Art\AuthBundle\Api\Service\Accounts;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements ProcessorInterface<TwoFactorDeactivation, null>
 */
final readonly class TwoFactorDeactivationProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentAccount $account,
        private TwoFactorPolicy $policy,
        private Accounts $accounts,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        // Where the sign-in demands it — production always does — it never goes.
        if ($this->policy->required()) {
            throw new AuthCodeException(Response::HTTP_FORBIDDEN, 'error.two_factor.required');
        }

        $account = $this->account->get();
        if (!$account->isTotpAuthenticationEnabled()) {
            throw new AuthCodeException(Response::HTTP_CONFLICT, 'error.two_factor.not_enabled');
        }

        $this->accounts->save($account->disableTotp());

        return null;
    }
}
