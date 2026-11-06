<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The end of a sign-in: the second factor or the enrolment has just passed.
 *
 * lexik sets the access-token cookie and gesdinet, which listens to lexik's success, the refresh cookie; both
 * are left out of the body. The sign-in is recorded in the account's realm (date, IP, browser), then the sign-in
 * session is emptied: it served its one purpose.
 */
final readonly class SessionCookies
{
    /** The sign-in session's note of the "stay signed in" box, from the password to the last step. */
    public const string REMEMBER = 'auth.remember';

    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandler $jwtSuccess,
        private Realms $realms,
        private ManagerRegistry $registry,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $data the body of the response — never a token
     */
    public function issue(ApiAccountInterface $account, Request $request, array $data): Response
    {
        // ⚠️ The body goes IN, not after: with both tokens out of the body, lexik turns a response without data
        // into a 204 — and a 204 loses whatever body is set on it later.
        $response = $this->jwtSuccess->handleAuthenticationSuccess($account, null, $data);

        // "Stay signed in" unticked: the cookies die with the browser. Ticked — the default of a client that says
        // nothing —, the refresh cookie keeps its lifetime.
        $remember = !$request->hasSession() || true === $request->getSession()->get(self::REMEMBER, true);
        if (!$remember) {
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->setCookie($cookie->withExpires(0));
            }
        }

        $this->record($account, $request);

        // Without a token, the firewall's context listener removes it from the session instead of writing it
        // back: nothing of the sign-in outlives its last step.
        $this->tokenStorage->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return $response;
    }

    private function record(ApiAccountInterface $account, Request $request): void
    {
        $realm = $this->realms->of($account);
        if (null === $realm) {
            throw new \LogicException(\sprintf('No realm of the API sign-in declares the account class "%s".', $account::class));
        }

        $manager = $this->registry->getManagerForClass($realm->loginRecordClass);
        if (null === $manager) {
            throw new \LogicException(\sprintf('No entity manager maps "%s".', $realm->loginRecordClass));
        }

        $userAgent = $request->headers->get('User-Agent');
        $manager->persist(new ($realm->loginRecordClass)($account, $request->getClientIp(), null === $userAgent ? null : mb_substr($userAgent, 0, 255)));
        $manager->flush();
    }
}
