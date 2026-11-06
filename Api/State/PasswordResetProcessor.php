<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\ApiResource\PasswordReset;
use Jul6Art\AuthBundle\Api\Service\PasswordWriter;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * @implements ProcessorInterface<PasswordReset, null>
 */
final readonly class PasswordResetProcessor implements ProcessorInterface
{
    public const string INVALID_TOKEN = 'error.password_reset.invalid_token';

    public function __construct(
        private ResetPasswordHelperInterface $resetPassword,
        private PasswordWriter $passwords,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        try {
            $account = $this->resetPassword->validateTokenAndFetchUser($data->token);
        } catch (ResetPasswordExceptionInterface) {
            $account = null;
        }

        if (!$account instanceof ApiAccountInterface || !$account->isActive()) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(self::INVALID_TOKEN, self::INVALID_TOKEN, [], $data, 'token', $data->token)]));
        }

        $this->resetPassword->removeResetRequest($data->token);
        $this->passwords->write($account, $data->password);

        return null;
    }
}
