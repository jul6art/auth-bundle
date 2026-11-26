<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Assistance\Assistance;
use Jul6Art\AuthBundle\Api\Assistance\Assistances;
use Jul6Art\AuthBundle\Api\Assistance\AssistanceStarted;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Api\Security\SessionCookies;
use Jul6Art\AuthBundle\Api\Security\UserChecker;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
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
 * `start` sends the browser to the provider — the code flow, with a state, a nonce and a PKCE challenge kept in a
 * cookie of their own ({@see PendingSignIn}) —; `callback` checks what comes back, exchanges the code, checks the
 * identity token, then sets the session cookies and sends the browser on. A refusal goes back to the sign-in page, its
 * code in `?error=`.
 *
 * ⚠️ Both routes must be in one of the realm's firewalls — its sign-in firewall, whose pattern lists them.
 */
final readonly class OidcSignIn
{
    public const string CALLBACK = '/api/auth/oidc/callback';

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
        private PendingSignIn $pending,
        private UserChecker $userChecker,
        private LoggerInterface $logger,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
    ) {
    }

    public function start(Request $request): Response
    {
        [$realm, $settings, $client] = $this->context($request);
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = self::base64Url(random_bytes(48));
        try {
            $endpoint = $this->provider->discovery($client ?? throw new OidcRefusal('auth.oidc.unavailable', 'no client of the provider for this request'))['authorization_endpoint'];
        } catch (OidcRefusal $refusal) {
            return $this->refused($settings, $refusal);
        }

        $response = new RedirectResponse($endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code',
            'client_id' => $client->clientId,
            'redirect_uri' => self::redirectUri($request),
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ]));
        $response->headers->setCookie($this->pending->cookie($realm, $state, $nonce, $verifier, '0' !== $request->query->get('remember')));

        return $response;
    }

    public function callback(Request $request): Response
    {
        [$realm, $settings, $client] = $this->context($request);
        $pending = $this->pending->of($request, $realm);
        try {
            if (null === $pending || !hash_equals($pending['state'], $request->query->getString('state'))) {
                throw new OidcRefusal('auth.oidc.invalid', 'an answer to no sign-in of this browser');
            }
            if ($request->query->has('error')) {
                throw new OidcRefusal('auth.oidc.denied', $request->query->getString('error'));
            }
            if (null === $client) {
                throw new OidcRefusal('auth.oidc.unavailable', 'no client of the provider for this request');
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
            return $this->pending->forget($this->refused($settings, $refusal));
        }

        // Someone acting for the account (the provider's controlled impersonation): a session of its own — ended at a set
        // time, never refreshed, its account's password and second factor out of reach.
        $assistance = null === $claims['actor'] ? null : new Assistance($claims['actor']['email'], $claims['actor']['name'], $this->clock->now()->modify('+'.Assistances::TTL.' seconds'));
        $issued = $this->cookies->issue($account, $request, [], null === $assistance && $pending['remember'], $assistance);
        $response = new RedirectResponse($settings->afterSignIn);
        foreach ($issued->headers->getCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
        if (null !== $assistance) {
            $this->logger->notice('An assisted session opened under {account}, for {assistant}, until {end}.', ['account' => $claims['email'], 'assistant' => $assistance->assistant, 'end' => $assistance->endsAt->format(\DATE_ATOM)]);
            $this->dispatcher->dispatch(new AssistanceStarted($account, $assistance));
        }

        return $this->pending->forget($response);
    }

    /**
     * The realm of the request, its settings and its client — none when neither a resolver nor its configuration tells
     * one: the sign-in is then refused in words. A realm that does not sign in through a provider: a 404.
     *
     * @return array{0: Realm, 1: OidcSettings, 2: OidcClient|null}
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

        return [$realm, $settings, $settings->client()];
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
