<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Jul6Art\AuthBundle\Api\ApiResource\TwoFactorSetup;
use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Jul6Art\AuthBundle\Api\Service\TotpEnrolment;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements ProviderInterface<TwoFactorSetup>
 */
final readonly class TwoFactorSetupProvider implements ProviderInterface
{
    public function __construct(
        private CurrentAccount $account,
        private TotpEnrolment $enrolment,
    ) {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TwoFactorSetup
    {
        $account = $this->account->get();
        // An active second factor never shows its key again.
        if ($account->isTotpAuthenticationEnabled()) {
            throw new AuthCodeException(Response::HTTP_CONFLICT, 'error.two_factor.already_enabled');
        }

        $this->enrolment->prepare($account);

        return new TwoFactorSetup($this->enrolment->qrCode($account), $this->enrolment->readableSecret($account));
    }
}
