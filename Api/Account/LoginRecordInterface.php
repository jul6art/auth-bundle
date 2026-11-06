<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Account;

/**
 * One completed sign-in — password AND second factor —, to detect an abnormal access: the date, the IP, the
 * browser. The application declares the entity, in its own base, with this constructor; it decides how long
 * it keeps it (core-bundle's `#[Purgeable]`).
 */
interface LoginRecordInterface
{
    public function __construct(ApiAccountInterface $account, ?string $ipAddress, ?string $userAgent);
}
