<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Account;

use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account the API sign-in can open: an e-mail address and a password, a second factor (TOTP and backup
 * codes), a language for its e-mails. The application declares its own entity, in its own base, and keeps
 * everything else on it; {@see TwoFactorAccountTrait} carries the second factor's columns.
 */
interface ApiAccountInterface extends UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    public function getEmail(): string;

    /**
     * The name the e-mails greet.
     */
    public function getName(): string;

    /**
     * A deactivated account signs in nowhere, and its tokens stop working at the next request.
     */
    public function isActive(): bool;

    /**
     * The language of its e-mails: a key of the realm's `reset_links`.
     */
    public function getPreferredLocale(): string;

    public function setPassword(string $hashedPassword): static;

    public function startTotpEnrolment(string $secret): static;

    public function isTotpEnrolmentPending(): bool;

    public function confirmTotp(\DateTimeImmutable $at): static;

    public function getTotpEnabledAt(): ?\DateTimeImmutable;

    /**
     * Switches the second factor off, its backup codes with it.
     */
    public function disableTotp(): static;

    /**
     * @param list<string> $codes the codes in clear, shown once to the account and never stored
     */
    public function replaceBackupCodes(array $codes): static;

    public function countBackupCodes(): int;
}
