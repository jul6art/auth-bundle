<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * A sign-in waiting for its second factor reached a route that needs more: it receives the step it
 * is missing, as a code.
 */
final class TwoFactorRequiredHandler implements AuthenticationRequiredHandlerInterface
{
    public function onAuthenticationRequired(Request $request, TokenInterface $token): JsonResponse
    {
        return new JsonResponse(['error' => 'error.two_factor.required'], Response::HTTP_UNAUTHORIZED);
    }
}
