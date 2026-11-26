<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Controller;

use Jul6Art\AuthBundle\Api\Oidc\OidcSignIn;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The sign-in through an OpenID provider ({@see OidcSignIn}): where the browser starts it, and where the provider sends
 * it back. Without a realm that enables it — or its packages —, both answer 404.
 */
#[AsController]
final readonly class OidcController
{
    public function __construct(
        private ?OidcSignIn $signIn = null,
    ) {
    }

    #[Route('/api/auth/oidc/start', name: 'auth_oidc_start', methods: ['GET'])]
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    public function start(Request $request): Response
    {
        return ($this->signIn ?? throw new NotFoundHttpException())->start($request);
    }

    #[Route(OidcSignIn::CALLBACK, name: 'auth_oidc_callback', methods: ['GET'])]
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    public function callback(Request $request): Response
    {
        return ($this->signIn ?? throw new NotFoundHttpException())->callback($request);
    }
}
