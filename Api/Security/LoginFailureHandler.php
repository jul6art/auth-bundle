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
 * A wrong address, a wrong password, a deactivated account: one answer for all three, so that it
 * never tells whether an address has an account. Only the rate limit has its own.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(['error' => 'error.login.too_many_attempts'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse(['error' => 'error.login.invalid_credentials'], Response::HTTP_UNAUTHORIZED);
    }
}
