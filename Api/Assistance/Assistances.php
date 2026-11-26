<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Assistance;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The assisted session of the request, if it is one: who acts for the account, and until when. An application reads it
 * to show who acts and to write both in its journal; the realm's own account routes refuse it (`AUTH_SELF`).
 *
 * Issued by the sign-in through a provider whose identity token names an actor (`act`): the access token then carries
 * it, ends {@see self::TTL} seconds later, and no refresh token goes with it.
 */
final readonly class Assistances
{
    /** How long an assisted session lasts: thirty minutes, never refreshed. */
    public const int TTL = 1800;

    /** The claim of the identity token and of the access token that names whoever acts (RFC 8693). */
    public const string CLAIM = 'act';

    /** Where the assistance waits: on the request while its access token is issued, on the token once authenticated. */
    public const string ATTRIBUTE = 'auth.assistance';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The assistance of the account signed in, if it is assisted.
     */
    public function current(): ?Assistance
    {
        $token = $this->tokenStorage->getToken();
        $assistance = null !== $token && $token->hasAttribute(self::ATTRIBUTE) ? $token->getAttribute(self::ATTRIBUTE) : null;

        return $assistance instanceof Assistance ? $assistance : null;
    }

    /**
     * The assistance an access token is being issued for, during the sign-in that opens it.
     */
    public function issuing(): ?Assistance
    {
        $assistance = $this->requestStack->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

        return $assistance instanceof Assistance ? $assistance : null;
    }
}
