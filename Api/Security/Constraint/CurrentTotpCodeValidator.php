<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security\Constraint;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Security\TwoFactorPolicy;
use Jul6Art\AuthBundle\Api\Service\TotpEnrolment;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class CurrentTotpCodeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly TotpEnrolment $enrolment,
        private readonly TwoFactorPolicy $policy,
    ) {
    }

    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CurrentTotpCode) {
            throw new UnexpectedTypeException($constraint, CurrentTotpCode::class);
        }

        $account = $this->security->getUser();
        if ($account instanceof ApiAccountInterface && \is_string($value) && $this->enrolment->isValidCode($account, $value)) {
            return;
        }

        // No second factor demanded here, none set up by the account: there is no code to ask.
        if ($account instanceof ApiAccountInterface && !$this->policy->appliesTo($account)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
