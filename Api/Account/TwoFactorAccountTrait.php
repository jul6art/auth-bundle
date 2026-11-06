<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Account;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Api\Security\BackupCodes;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

/**
 * The second factor of an {@see ApiAccountInterface}: the TOTP secret, encrypted at rest (core-bundle's
 * `encrypted_string`), the date it was confirmed, and the SHA-256 hashes of the backup codes still unused.
 * The column names of admin-bundle's `TwoFactorTrait` and `TwoFactorBackupCodesTrait`.
 *
 * The secret is set at enrolment and protects nothing while `totpEnabledAt` is empty. It is never printed.
 */
trait TwoFactorAccountTrait
{
    #[ORM\Column(name: 'totp_secret', type: EncryptedStringType::NAME, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'totp_enabled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $totpEnabledAt = null;

    /**
     * @var list<string>|null SHA-256 hashes of the backup codes still unused
     */
    #[ORM\Column(name: 'totp_backup_codes', type: Types::JSON, nullable: true)]
    private ?array $totpBackupCodes = null;

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret && $this->totpEnabledAt instanceof \DateTimeImmutable;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        // SHA-1, 30 seconds, six digits: the only parameters every authenticator app reads right.
        return null === $this->totpSecret ? null : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function startTotpEnrolment(string $secret): static
    {
        $this->totpSecret = $secret;
        $this->totpEnabledAt = null;

        return $this;
    }

    public function isTotpEnrolmentPending(): bool
    {
        return null !== $this->totpSecret && !$this->totpEnabledAt instanceof \DateTimeImmutable;
    }

    public function confirmTotp(\DateTimeImmutable $at): static
    {
        $this->totpEnabledAt = $at;

        return $this;
    }

    public function getTotpEnabledAt(): ?\DateTimeImmutable
    {
        return $this->totpEnabledAt;
    }

    public function disableTotp(): static
    {
        $this->totpSecret = null;
        $this->totpEnabledAt = null;
        $this->totpBackupCodes = null;

        return $this;
    }

    public function isBackupCode(string $code): bool
    {
        $hash = BackupCodes::hash($code);

        return array_any($this->totpBackupCodes ?? [], static fn (string $stored): bool => hash_equals($stored, $hash));
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = BackupCodes::hash($code);
        $this->totpBackupCodes = array_values(array_filter($this->totpBackupCodes ?? [], static fn (string $stored): bool => !hash_equals($stored, $hash)));
    }

    /**
     * @param list<string> $codes
     */
    public function replaceBackupCodes(array $codes): static
    {
        $this->totpBackupCodes = array_map(BackupCodes::hash(...), $codes);

        return $this;
    }

    public function countBackupCodes(): int
    {
        return \count($this->totpBackupCodes ?? []);
    }
}
