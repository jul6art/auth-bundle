<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Security;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The login limiter of a firewall that also carries a token: it counts the sign-in requests, and
 * only them.
 *
 * ⚠️ **Symfony's `login_throttling` limits the FIREWALL.** Its listener checks the limiter on every
 * passport that carries a `UserBadge`, and consumes it on every failure, whatever the authenticator.
 * On a firewall that also reads a JWT, an expired or forged token is a failure too: it spends the
 * budget of the client's IP, and once that budget is gone every request from the IP is refused,
 * valid tokens included. Twenty-five requests with a made-up token lock an office out of the API for
 * a minute. This limiter answers "accepted" outside the configured paths, so Symfony's plumbing is
 * kept (check, consumption on failure, reset on success) and only its reach is corrected.
 *
 * Two budgets, as Symfony's `DefaultLoginRateLimiter` keeps them:
 * - per IP, every identifier together — it stops the sweep that tries one address per request;
 * - per (identifier, IP) — it stops the guessing of one account's password.
 *
 * ⚠️ A successful sign-in clears the second only. The IP budget bounds how many identifiers one
 * client may try, so it must survive the success of any one of them: clearing both let a client
 * that owns one account reset its sweep at will. It only gets back the token the success itself
 * took, when Symfony's listener took one (see reset()).
 *
 * The keys are hashed with the application's secret, so the cache holds no address in the clear.
 */
final class SignInRateLimiter extends AbstractRequestRateLimiter
{
    /**
     * Set by Symfony's LoginThrottlingListener (its private CONSUMED_ATTRIBUTE, since 7.4.24 and 8.1.12) when it
     * consumed the limiter before the credentials were checked.
     */
    private const string CONSUMED_BY_THE_LISTENER = '_security_login_throttling_consumed';

    /**
     * @param list<string>     $paths  the path prefixes where a request is a sign-in attempt
     * @param non-empty-string $secret the secret the keys are hashed with
     */
    public function __construct(
        private readonly RateLimiterFactoryInterface $globalFactory,
        private readonly RateLimiterFactoryInterface $localFactory,
        private readonly array $paths,
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
        if ([] === $paths) {
            throw new \InvalidArgumentException('The sign-in limiter needs at least one path: it would count nothing.');
        }
    }

    #[\Override]
    public function reset(Request $request): void
    {
        if (!$this->isSignIn($request)) {
            return;
        }

        $this->localLimiter($request)->reset();

        // Since Symfony 7.4.24 and 8.1.12, on a stateful firewall, the throttling listener consumes BEFORE the
        // credentials are checked, so that concurrent attempts count too: a successful one included. That token goes
        // back to the IP budget, as Symfony's DefaultLoginRateLimiter gives it back; kept, every success spent the
        // IP budget, and one office signing in all day long was locked out. Before, a success consumed nothing and
        // nothing is given back: a token returned then would let a client that owns one account refill its sweep.
        if (true === $request->attributes->get(self::CONSUMED_BY_THE_LISTENER)) {
            $this->globalLimiter($request)->consume(-1);
        }
    }

    /**
     * @return list<LimiterInterface>
     */
    #[\Override]
    protected function getLimiters(Request $request): array
    {
        // No limiter at all: the base class reads an empty list as "accepted", which is what a
        // request that presents a token is — it is not an attempt.
        if (!$this->isSignIn($request)) {
            return [];
        }

        return [
            $this->globalLimiter($request),
            $this->localLimiter($request),
        ];
    }

    private function globalLimiter(Request $request): LimiterInterface
    {
        return $this->globalFactory->create($this->hash((string) $request->getClientIp()));
    }

    private function localLimiter(Request $request): LimiterInterface
    {
        // The identifier the authenticator read, put on the request by the throttling listener. Empty
        // on a malformed body: then the IP budget still applies, and a flood of them is still a flood.
        $identifier = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $identifier = \is_string($identifier) ? $identifier : '';
        $identifier = 1 === preg_match('//u', $identifier) ? mb_strtolower($identifier, 'UTF-8') : strtolower($identifier);

        return $this->localFactory->create($this->hash($identifier.'-'.$request->getClientIp()));
    }

    private function isSignIn(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach ($this->paths as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
