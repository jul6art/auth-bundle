<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Http\AuthCodeException;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Security\BackupCodes;
use Jul6Art\AuthBundle\Api\Security\TwoFactorThrottle;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/**
 * The second factor of an account, from enrolment to its backup codes: a secret is drawn once and kept pending
 * until a code proves the authenticator app has it. The app shows the realm's name (its issuer), so that an
 * account of two applications tells their codes apart.
 */
final readonly class TotpEnrolment
{
    public function __construct(
        private TotpAuthenticatorInterface $totp,
        private Realms $realms,
        private Accounts $accounts,
        private ClockInterface $clock,
        private ?TwoFactorThrottle $throttle = null,
    ) {
    }

    /**
     * Draws the secret on the first call only: reloading the enrolment screen must not change the QR code the
     * account may already have scanned.
     */
    public function prepare(ApiAccountInterface $account): void
    {
        if (!$account->getTotpAuthenticationConfiguration() instanceof TotpConfigurationInterface) {
            $account->startTotpEnrolment($this->totp->generateSecret());
            $this->accounts->save($account);
        }
    }

    /**
     * The QR code as a `data:` URI (SVG): no external resource, nothing to fetch.
     */
    public function qrCode(ApiAccountInterface $account): string
    {
        return new Builder(writer: new SvgWriter(), data: $this->provisioningUri($account), size: 220, margin: 8)->build()->getDataUri();
    }

    /**
     * The secret by groups of four, for the account that types it instead of scanning.
     */
    public function readableSecret(ApiAccountInterface $account): string
    {
        return implode(' ', str_split((string) $account->getTotpAuthenticationConfiguration()?->getSecret(), 4));
    }

    /**
     * Whether the code is the app's current one — and every answer spends or clears the account's budget
     * of wrong codes ({@see TwoFactorThrottle}): the enrolment, the activation and `#[CurrentTotpCode]` all
     * come through here.
     *
     * @throws AuthCodeException a 429 once the budget is spent, before the code is read
     */
    public function isValidCode(ApiAccountInterface $account, string $code): bool
    {
        if (!$account->getTotpAuthenticationConfiguration() instanceof TotpConfigurationInterface) {
            return false;
        }

        $this->throttle?->guard($account);
        $valid = $this->totp->checkCode($account, $code);
        if ($valid) {
            $this->throttle?->succeeded($account);
        } else {
            $this->throttle?->failed($account);
        }

        return $valid;
    }

    /**
     * Confirms a pending enrolment with a code from the app, and draws the backup codes.
     *
     * @return list<string>|null the backup codes in clear, to show once; null when the code is wrong
     */
    public function confirm(ApiAccountInterface $account, string $code): ?array
    {
        if (!$account->isTotpEnrolmentPending() || !$this->isValidCode($account, $code)) {
            return null;
        }

        $account->confirmTotp(new \DateTimeImmutable());

        return $this->regenerateBackupCodes($account);
    }

    /**
     * @return list<string> the new codes in clear; the previous ones stop working
     */
    public function regenerateBackupCodes(ApiAccountInterface $account): array
    {
        $codes = BackupCodes::generate();
        $account->replaceBackupCodes($codes);
        $this->accounts->save($account);

        return $codes;
    }

    private function provisioningUri(ApiAccountInterface $account): string
    {
        $configuration = $account->getTotpAuthenticationConfiguration();
        if (!$configuration instanceof TotpConfigurationInterface || '' === $configuration->getSecret()) {
            throw new \LogicException('No secret to show: prepare() the enrolment first.');
        }

        $period = $configuration->getPeriod();
        $digest = $configuration->getAlgorithm();
        $digits = $configuration->getDigits();
        if ($period < 1 || $digits < 1 || '' === $digest) {
            throw new \LogicException('A TOTP configuration needs a period, a digest and a number of digits.');
        }

        $totp = TOTP::create($configuration->getSecret(), $period, $digest, $digits, clock: $this->clock);
        $totp->setLabel($account->getUserIdentifier());

        $issuer = $this->realms->of($account)?->issuer;
        if (null !== $issuer && '' !== $issuer) {
            $totp->setIssuer($issuer);
        }

        return $totp->getProvisioningUri();
    }
}
