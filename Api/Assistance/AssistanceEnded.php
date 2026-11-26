<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Assistance;

/**
 * An assisted session signed out before its end: whoever acted left. One that reaches its end without signing out
 * dispatches nothing — its access token simply stops opening anything, at {@see Assistance::$endsAt}.
 */
final readonly class AssistanceEnded
{
    public function __construct(
        public string $account,
        public Assistance $assistance,
    ) {
    }
}
