<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account;

use Symfony\Component\Mime\Address;

/**
 * What a "forgot password" e-mail is made of, whoever sends it: the API's realm, or a web screen of admin-bundle.
 *
 * The subject is a translation key, translated in the e-mail's language — never the request's: the e-mail may
 * leave from a worker, after the request.
 */
final readonly class PasswordResetEmail
{
    /**
     * @param array<string, mixed>  $context           the templates' variables
     * @param array<string, string> $subjectParameters
     */
    public function __construct(
        public Address $from,
        public Address $to,
        public string $subject,
        public string $translationDomain,
        public string $htmlTemplate,
        public array $context = [],
        public ?string $textTemplate = null,
        public ?string $locale = null,
        public array $subjectParameters = [],
    ) {
    }
}
