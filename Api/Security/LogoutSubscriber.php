<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Jul6Art\AuthBundle\Api\Assistance\Assistance;
use Jul6Art\AuthBundle\Api\Assistance\AssistanceEnded;
use Jul6Art\AuthBundle\Api\Assistance\Assistances;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * `POST /api/logout`: gesdinet has revoked the refresh token by then (its own listener,
 * at the default priority); this one answers 204 and clears BOTH cookies — the access token's too,
 * which gesdinet does not know about. An assisted session's sign-out dispatches {@see AssistanceEnded}.
 */
#[AsEventListener(event: LogoutEvent::class, priority: -64)]
final class LogoutSubscriber
{
    public const string ACCESS_COOKIE = '__Host-access_token';

    public const string REFRESH_COOKIE = 'refresh_token';

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        // An assisted session signed out: whoever acted left — before its end.
        $token = $event->getToken();
        $assistance = null !== $token && $token->hasAttribute(Assistances::ATTRIBUTE) ? $token->getAttribute(Assistances::ATTRIBUTE) : null;
        if (null !== $token && $assistance instanceof Assistance) {
            $this->dispatcher->dispatch(new AssistanceEnded($token->getUserIdentifier(), $assistance));
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie(self::ACCESS_COOKIE, '/', null, true, true, Cookie::SAMESITE_STRICT);
        $response->headers->clearCookie(self::REFRESH_COOKIE, '/api', null, true, true, Cookie::SAMESITE_STRICT);

        $event->setResponse($response);
    }
}
