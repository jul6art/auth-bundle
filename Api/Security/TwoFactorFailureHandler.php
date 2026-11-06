<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A wrong code, from the app or among the backup codes. The sign-in session stays open: the next
 * code may be right — within the firewall's rate limit.
 */
final class TwoFactorFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(['error' => 'error.login.too_many_attempts'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse(['error' => 'error.two_factor.invalid_code'], Response::HTTP_UNAUTHORIZED);
    }
}
