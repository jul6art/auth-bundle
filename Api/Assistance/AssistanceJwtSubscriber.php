<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Assistance;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The access token of an assisted session: issued, it names whoever acts (`act`) and ends with the assistance, whatever
 * the realm's lifetime; read back, it marks the security token, which {@see Assistances::current()} reads.
 */
final readonly class AssistanceJwtSubscriber
{
    public function __construct(
        private Assistances $assistances,
    ) {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onCreated(JWTCreatedEvent $event): void
    {
        $assistance = $this->assistances->issuing();
        if (null === $assistance) {
            return;
        }
        $event->setData([Assistances::CLAIM => $assistance->claim(), 'exp' => $assistance->endsAt->getTimestamp()] + $event->getData());
    }

    #[AsEventListener(event: Events::JWT_AUTHENTICATED)]
    public function onAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $assistance = Assistance::fromPayload($event->getPayload());
        if (null !== $assistance) {
            $event->getToken()->setAttribute(Assistances::ATTRIBUTE, $assistance);
        }
    }
}
