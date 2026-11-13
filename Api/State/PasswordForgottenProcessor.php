<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Account\PasswordResetMailer;
use Jul6Art\AuthBundle\Api\ApiResource\PasswordForgotten;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Service\Accounts;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * @implements ProcessorInterface<PasswordForgotten, null>
 */
final readonly class PasswordForgottenProcessor implements ProcessorInterface
{
    public function __construct(
        private Realms $realms,
        private Accounts $accounts,
        private ResetPasswordHelperInterface $resetPassword,
        private PasswordResetMailer $mailer,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $realm = $this->realms->current();
        $account = null === $realm ? null : $this->accounts->findByEmail($realm, $data->email);
        if (null === $realm || null === $account || !$account->isActive()) {
            return null;
        }

        try {
            $token = $this->resetPassword->generateResetToken($account);
        } catch (ResetPasswordExceptionInterface) {
            // A request already pending (the throttle): the first link is still valid, and the answer must stay
            // the same as for an unknown address.
            return null;
        }

        $this->mailer->send($realm, $account, $token->getToken());

        return null;
    }
}
