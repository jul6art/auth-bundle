<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account\Event;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * An account `app:user:create` has built — address, names, roles, password — and is about to write.
 *
 * A listener completes it in the same flush: the personal space a customer account is born with, an address taken
 * as confirmed because whoever runs the command on the server vouches for it. What it persists is written with
 * the account.
 */
final readonly class AccountCreationEvent
{
    public function __construct(
        public PasswordAuthenticatedUserInterface $account,
        public bool $admin,
    ) {
    }
}
