<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * The second factor passed: the sign-in is complete, the two cookies are set.
 */
final readonly class TwoFactorSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private SessionCookies $sessionCookies,
    ) {
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $account = $token->getUser();
        if (!$account instanceof ApiAccountInterface) {
            return new JsonResponse(['error' => 'error.auth.required'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->sessionCookies->issue($account, $request, ['status' => 'status.signed_in']);
    }
}
