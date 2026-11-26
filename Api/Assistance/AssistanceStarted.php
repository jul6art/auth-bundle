<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Assistance;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;

/**
 * An assisted session opened under an account: an application that keeps a journal writes both — the account, and
 * whoever acts for it. Dispatched by the sign-in through the provider, once its cookies are issued.
 */
final readonly class AssistanceStarted
{
    public function __construct(
        public ApiAccountInterface $account,
        public Assistance $assistance,
    ) {
    }
}
