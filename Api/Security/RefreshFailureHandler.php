<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * The refresh token is missing, expired, already used or revoked: the front goes back to the
 * sign-in.
 */
final class RefreshFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(['error' => 'error.auth.session_expired'], Response::HTTP_UNAUTHORIZED);
    }
}
