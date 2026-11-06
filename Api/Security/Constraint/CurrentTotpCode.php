<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A current code of the signed-in account's authenticator app: what a sensitive action on the second factor
 * asks for. Where the second factor applies to nobody — and the account has none —, there is no code to ask.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CurrentTotpCode extends Constraint
{
    public string $message = 'error.two_factor.invalid_code';
}
