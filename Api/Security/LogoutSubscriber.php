<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * `POST /api/logout`: gesdinet has revoked the refresh token by then (its own listener,
 * at the default priority); this one answers 204 and clears BOTH cookies — the access token's too,
 * which gesdinet does not know about.
 */
#[AsEventListener(event: LogoutEvent::class, priority: -64)]
final class LogoutSubscriber
{
    public const string ACCESS_COOKIE = '__Host-access_token';

    public const string REFRESH_COOKIE = 'refresh_token';

    public function __invoke(LogoutEvent $event): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie(self::ACCESS_COOKIE, '/', null, true, true, Cookie::SAMESITE_STRICT);
        $response->headers->clearCookie(self::REFRESH_COOKIE, '/api', null, true, true, Cookie::SAMESITE_STRICT);

        $event->setResponse($response);
    }
}
