<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Controller;

use Jul6Art\AuthBundle\Api\Assistance\Assistances;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Whether the session is an assisted one, for the screens' banner: who acts for the account, and until when — 204 for
 * the account's own session.
 */
#[AsController]
final readonly class AssistanceController
{
    public function __construct(
        private Assistances $assistances,
    ) {
    }

    #[Route('/api/auth/assistance', name: 'auth_assistance', methods: ['GET'])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function show(): Response
    {
        $assistance = $this->assistances->current();
        if (null === $assistance) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(['assistant' => $assistance->claim(), 'endsAt' => $assistance->endsAt->format(\DATE_ATOM)], headers: ['Cache-Control' => 'no-store']);
    }
}
