<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Jul6Art\AuthBundle\Account\PasswordResetMailer;
use Jul6Art\AuthBundle\Account\ResponseFloor;
use Jul6Art\AuthBundle\Api\ApiResource\PasswordForgotten;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Service\Accounts;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * `POST /api/password/forgot`. ⚠️ **The answer never tells whether the address has an account**, by its content nor
 * by its duration: always 202 and no body, never sooner than `auth.api.password_reset.response_floor_ms`
 * ({@see ResponseFloor}). An unknown, inactive or throttled account and a mail that could not leave are silent alike.
 *
 * @implements ProcessorInterface<PasswordForgotten, null>
 */
final readonly class PasswordForgottenProcessor implements ProcessorInterface
{
    public function __construct(
        private Realms $realms,
        private Accounts $accounts,
        private ResetPasswordHelperInterface $resetPassword,
        private PasswordResetMailer $mailer,
        private ResponseFloor $floor,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->floor->hold(function () use ($data): void {
            $this->request($data->email);
        });

        return null;
    }

    private function request(string $email): void
    {
        $realm = $this->realms->current();
        $account = null === $realm ? null : $this->accounts->findByEmail($realm, $email);
        if (null === $realm || null === $account || !$account->isActive()) {
            return;
        }

        try {
            $token = $this->resetPassword->generateResetToken($account);
        } catch (ResetPasswordExceptionInterface) {
            // A request already pending (the throttle): the first link is still valid, and the answer must stay
            // the same as for an unknown address.
            return;
        }

        try {
            $this->mailer->send($realm, $account, $token->getToken());
        } catch (TransportExceptionInterface $exception) {
            // A mailer that sends at once (no Messenger) and fails: a 500 for a known address only would tell it.
            $this->logger->error('The forgotten-password e-mail could not leave.', ['exception' => $exception]);
        }
    }
}
