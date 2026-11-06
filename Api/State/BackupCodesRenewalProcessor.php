<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Api\ApiResource\BackupCodes;
use Jul6Art\AuthBundle\Api\ApiResource\BackupCodesRenewal;
use Jul6Art\AuthBundle\Api\Service\TotpEnrolment;

/**
 * @implements ProcessorInterface<BackupCodesRenewal, BackupCodes>
 */
final readonly class BackupCodesRenewalProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentAccount $account,
        private TotpEnrolment $enrolment,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BackupCodes
    {
        return new BackupCodes($this->enrolment->regenerateBackupCodes($this->account->get()));
    }
}
