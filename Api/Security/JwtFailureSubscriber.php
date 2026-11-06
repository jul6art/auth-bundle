<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An access token that lexik refuses — expired, forged, for a deactivated account — answers a code
 * like the rest of the API. "Expired" has its own, because it is the one a front answers with a
 * refresh.
 */
final class JwtFailureSubscriber
{
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(new JsonResponse(['error' => 'error.auth.expired'], Response::HTTP_UNAUTHORIZED));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onInvalid(AuthenticationFailureEvent $event): void
    {
        if ($event instanceof JWTExpiredEvent) {
            return;
        }

        $event->setResponse(new JsonResponse(['error' => 'error.auth.required'], Response::HTTP_UNAUTHORIZED));
    }
}
