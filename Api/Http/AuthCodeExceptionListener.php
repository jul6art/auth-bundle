<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers an {@see AuthCodeException} as `{"error": "<code>"}`, before API Platform turns it into a problem.
 */
final class AuthCodeExceptionListener
{
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof AuthCodeException) {
            $event->setResponse(new JsonResponse(['error' => $exception->errorCode], $exception->status));
        }
    }
}
