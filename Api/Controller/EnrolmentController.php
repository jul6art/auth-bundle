<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Controller;

use Jul6Art\AuthBundle\Api\Security\AuthVoter;
use Jul6Art\AuthBundle\Api\Security\SessionCookies;
use Jul6Art\AuthBundle\Api\Service\TotpEnrolment;
use Jul6Art\AuthBundle\Api\State\CurrentAccount;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The enrolment of the second factor at the first sign-in, in the sign-in session: the QR code to scan, then a
 * code from the app, which completes the sign-in and hands the ten backup codes — shown once.
 */
#[AsController]
final readonly class EnrolmentController
{
    public function __construct(
        private CurrentAccount $account,
        private TotpEnrolment $enrolment,
        private SessionCookies $sessionCookies,
    ) {
    }

    #[Route('/api/enrolment', name: 'auth_enrolment_show', methods: ['GET'])]
    #[IsGranted(AuthVoter::ENROL)]
    public function show(): JsonResponse
    {
        $account = $this->account->get();
        $this->enrolment->prepare($account);

        return new JsonResponse([
            'qrCode' => $this->enrolment->qrCode($account),
            'secret' => $this->enrolment->readableSecret($account),
        ]);
    }

    #[Route('/api/enrolment', name: 'auth_enrolment_confirm', methods: ['POST'])]
    #[IsGranted(AuthVoter::ENROL)]
    public function confirm(Request $request): Response
    {
        $account = $this->account->get();
        $payload = json_decode($request->getContent(), true);
        $code = \is_array($payload) && \is_string($payload['code'] ?? null) ? $payload['code'] : '';

        $backupCodes = $this->enrolment->confirm($account, $code);
        if (null === $backupCodes) {
            return new JsonResponse(['error' => 'error.two_factor.invalid_code'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->sessionCookies->issue($account, $request, ['backupCodes' => $backupCodes]);
    }
}
