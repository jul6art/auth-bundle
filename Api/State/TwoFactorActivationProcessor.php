<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Api\ApiResource\BackupCodes;
use Jul6Art\AuthBundle\Api\ApiResource\TwoFactorActivation;
use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Jul6Art\AuthBundle\Api\Service\TotpEnrolment;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements ProcessorInterface<TwoFactorActivation, BackupCodes>
 */
final readonly class TwoFactorActivationProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentAccount $account,
        private TotpEnrolment $enrolment,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BackupCodes
    {
        $account = $this->account->get();
        if ($account->isTotpAuthenticationEnabled()) {
            throw new AuthCodeException(Response::HTTP_CONFLICT, 'error.two_factor.already_enabled');
        }

        // Null: no pending setup (GET …/setup first), or a wrong code.
        $backupCodes = $this->enrolment->confirm($account, $data->code);
        if (null === $backupCodes) {
            throw new AuthCodeException(Response::HTTP_UNPROCESSABLE_ENTITY, 'error.two_factor.invalid_code');
        }

        return new BackupCodes($backupCodes);
    }
}
