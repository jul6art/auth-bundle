<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Security\SessionCookies;
use Jul6Art\AuthBundle\Api\Security\UserChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * A realm's sign-in through an OpenID provider: the provider checks who signs in — its password, its second factor —,
 * the realm finds its account of the same address and opens the same session as its own sign-in, without asking its
 * second factor again (the provider's `amr` says it was checked).
 *
 * `start` sends the browser to the provider — the code flow, with a state, a nonce and a PKCE challenge kept in the
 * sign-in session —; `callback` checks what comes back, exchanges the code, checks the identity token, then sets the
 * session cookies and sends the browser on. A refusal goes back to the sign-in page, its code in `?error=`.
 *
 * ⚠️ Both routes must be in the realm's sign-in firewall, the one with a session: its pattern lists them.
 */
final readonly class OidcSignIn
{
    public const string CALLBACK = '/api/auth/oidc/callback';

    private const string SESSION = 'auth.oidc';

    /**
     * @param iterable<OidcClientResolver> $resolvers
     */
    public function __construct(
        private Realms $realms,
        #[AutowireIterator(OidcClientResolver::TAG)]
        private iterable $resolvers,
        private OidcProvider $provider,
        private IdTokenVerifier $verifier,
        private ManagerRegistry $registry,
        private SessionCookies $cookies,
        private UserChecker $userChecker,
        private LoggerInterface $logger,
    ) {
    }

    public function start(Request $request): Response
    {
        [, $settings, $client] = $this->context($request);
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = self::base64Url(random_bytes(48));
        $session = $request->getSession();
        $session->set(self::SESSION, ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier]);
        $session->set(SessionCookies::REMEMBER, '0' !== $request->query->get('remember'));
        try {
            $endpoint = $this->provider->discovery($client)['authorization_endpoint'];
        } catch (OidcRefusal $refusal) {
            return $this->refused($settings, $refusal);
        }

        return new RedirectResponse($endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code',
            'client_id' => $client->clientId,
            'redirect_uri' => self::redirectUri($request),
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(Request $request): Response
    {
        [$realm, $settings, $client] = $this->context($request);
        $session = $request->getSession();
        $pending = $session->get(self::SESSION);
        $session->remove(self::SESSION);
        try {
            if (!\is_array($pending) || !\is_string($pending['state'] ?? null) || !\is_string($pending['nonce'] ?? null) || !\is_string($pending['verifier'] ?? null) || !hash_equals($pending['state'], $request->query->getString('state'))) {
                throw new OidcRefusal('auth.oidc.invalid', 'an answer to no sign-in of this browser');
            }
            if ($request->query->has('error')) {
                throw new OidcRefusal('auth.oidc.denied', $request->query->getString('error'));
            }
            $tokens = $this->provider->token($client, $request->query->getString('code'), self::redirectUri($request), $pending['verifier']);
            $claims = $this->verifier->verify($client, \is_string($tokens['id_token'] ?? null) ? $tokens['id_token'] : '', $pending['nonce'], $settings->methods);
            $account = $this->account($realm, $claims['email']) ?? throw new OidcRefusal('auth.oidc.no_account');
            try {
                $this->userChecker->checkPreAuth($account);
                $this->userChecker->checkPostAuth($account);
            } catch (AccountStatusException $exception) {
                throw new OidcRefusal('auth.oidc.inactive', '', $exception);
            }
        } catch (OidcRefusal $refusal) {
            return $this->refused($settings, $refusal);
        }

        $issued = $this->cookies->issue($account, $request, []);
        $response = new RedirectResponse($settings->afterSignIn);
        foreach ($issued->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * The realm of the request, its settings and its client — or nothing here: a 404.
     *
     * @return array{0: Realm, 1: OidcSettings, 2: OidcClient}
     */
    private function context(Request $request): array
    {
        $realm = $this->realms->current();
        $settings = $realm?->oidc;
        if (null === $realm || null === $settings) {
            throw new NotFoundHttpException();
        }
        foreach ($this->resolvers as $resolver) {
            $client = $resolver->client($realm, $request);
            if (null !== $client) {
                return [$realm, $settings, $client];
            }
        }

        return [$realm, $settings, $settings->client() ?? throw new NotFoundHttpException()];
    }

    private function account(Realm $realm, string $email): ?ApiAccountInterface
    {
        $account = $this->registry->getManagerForClass($realm->userClass)?->getRepository($realm->userClass)->findOneBy(['email' => $email]);

        return $account instanceof ApiAccountInterface ? $account : null;
    }

    private function refused(OidcSettings $settings, OidcRefusal $refusal): RedirectResponse
    {
        $this->logger->notice('A sign-in through the OpenID provider was refused: {reason}', ['reason' => $refusal->getMessage()]);

        return new RedirectResponse($settings->signInPage.(str_contains($settings->signInPage, '?') ? '&' : '?').'error='.rawurlencode($refusal->error));
    }

    private static function redirectUri(Request $request): string
    {
        return $request->getSchemeAndHttpHost().self::CALLBACK;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
