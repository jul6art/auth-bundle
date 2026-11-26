<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Account;

/**
 * A sign-in record that also keeps who signed in for the account: an assisted session, the provider's controlled
 * impersonation. Optional — a record without it keeps the account alone, whoever acted then in the logs only.
 */
interface AssistedLoginRecordInterface extends LoginRecordInterface
{
    /**
     * @param string $assistant the address of whoever acts for the account
     */
    public function assistedBy(string $assistant): void;
}
