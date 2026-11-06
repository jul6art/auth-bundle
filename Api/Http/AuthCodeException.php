<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Http;

/**
 * A refusal of the sign-in's routes: an HTTP status and a code the front translates, never a sentence.
 */
final class AuthCodeException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
    ) {
        parent::__construct($errorCode);
    }
}
