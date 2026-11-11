<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Every failed authentication of an API firewall answers the same thing — except "slow down", which
 * has to be told apart.
 *
 * ⚠️ **The authenticators each tell their own story.** A JSON sign-in says "Invalid credentials", a
 * refresh says "JWT Refresh Token Not Found" for a token that never existed and "Invalid JWT Refresh
 * Token" for one that expired, a JWT says "Expired JWT Token": distinctions the application never has
 * a reason to publish, sitting in vendors nobody re-reads, and a bundle upgrade adds the next one
 * without a test noticing. Normalising at the edge — one listener, every authenticator of the
 * firewall — makes that class of leak impossible rather than merely absent today.
 *
 * ⚠️ **429 is the one distinction kept.** Being throttled is not an oracle: the counter rises with the
 * attempts of an (identifier, IP) pair, whether the account exists or not. And hiding it costs:
 * Symfony's throttling otherwise renders as a plain 401, so a client cannot tell "wrong password" from
 * "locked out for a minute" — it retries at once, is locked out further, and shows a wrong-password
 * error. 429 with `Retry-After` is an answer a client can act on.
 *
 * A listener rather than a `failure_handler`: a handler is configured per authenticator, so the JSON
 * sign-in, the refresh and the JWT would each need one, and an authenticator added later would keep
 * its bundle's default. The listener sees the whole firewall, the unit the rule applies to. It leaves
 * the other firewalls alone: a form login redirects and flashes a translated message.
 *
 * `code` and `message` keep the shape LexikJWT answers with, so clients that read them keep working;
 * `errorCode` is the stable value a client maps.
 */
final readonly class ApiAuthenticationFailureNormalizer
{
    /** Nothing beyond "these credentials did not work": not which field, not whether the account exists. */
    public const string INVALID_CREDENTIALS_MESSAGE = 'Invalid credentials.';

    public const string RATE_LIMITED_MESSAGE = 'Too many attempts. Try again later.';

    /**
     * @param list<string> $firewalls the firewalls whose clients read JSON
     */
    public function __construct(
        private array $firewalls,
        private string $invalidCredentialsCode,
        private string $rateLimitedCode,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        if (!\in_array($event->getFirewallName(), $this->firewalls, true)) {
            return;
        }

        $exception = $event->getException();
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $event->setResponse(new JsonResponse(
                ['code' => Response::HTTP_TOO_MANY_REQUESTS, 'errorCode' => $this->rateLimitedCode, 'message' => self::RATE_LIMITED_MESSAGE],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) self::retryAfter($exception)],
            ));

            return;
        }

        $event->setResponse(new JsonResponse(
            ['code' => Response::HTTP_UNAUTHORIZED, 'errorCode' => $this->invalidCredentialsCode, 'message' => self::INVALID_CREDENTIALS_MESSAGE],
            Response::HTTP_UNAUTHORIZED,
        ));
    }

    /**
     * The seconds until the window ends, from the minutes the limiter put in the exception; a minute
     * when it gave none — the shortest honest answer for Symfony's default window.
     */
    private static function retryAfter(TooManyLoginAttemptsAuthenticationException $exception): int
    {
        $minutes = $exception->getMessageData()['%minutes%'] ?? null;

        return 60 * max(1, \is_int($minutes) ? $minutes : 1);
    }
}
