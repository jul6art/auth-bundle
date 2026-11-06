<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Api\ApiResource\PasswordChange;
use Jul6Art\AuthBundle\Api\Service\PasswordWriter;

/**
 * @implements ProcessorInterface<PasswordChange, null>
 */
final readonly class PasswordChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentAccount $account,
        private PasswordWriter $passwords,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->passwords->write($this->account->get(), $data->newPassword);

        return null;
    }
}
