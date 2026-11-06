<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * The password was right. That opens the sign-in session, and nothing else: the answer says which step comes
 * next, and never carries a token — unless the second factor does not apply to the account.
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TwoFactorPolicy $policy,
        private SessionCookies $sessionCookies,
    ) {
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // "Stay signed in": noted for the last step of the sign-in, which sets the cookies.
        if ($request->hasSession()) {
            $payload = json_decode($request->getContent(), true);
            $request->getSession()->set(SessionCookies::REMEMBER, !\is_array($payload) || false !== ($payload['remember'] ?? true));
        }

        if ($token instanceof TwoFactorTokenInterface) {
            return new JsonResponse(['status' => 'status.two_factor.required']);
        }

        $account = $token->getUser();
        // The second factor switched off (a workstation): the password alone signs in.
        if ($account instanceof ApiAccountInterface && !$this->policy->appliesTo($account)) {
            return $this->sessionCookies->issue($account, $request, ['status' => 'status.signed_in']);
        }

        if ($account instanceof ApiAccountInterface && !$account->isTotpAuthenticationEnabled()) {
            return new JsonResponse(['status' => 'status.two_factor.enrolment_required']);
        }

        // An enrolled account that skipped the second factor is a misconfiguration, never a pass.
        $this->tokenStorage->setToken(null);

        return new JsonResponse(['error' => 'error.two_factor.required'], Response::HTTP_UNAUTHORIZED);
    }
}
