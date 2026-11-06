<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * What an anonymous request on a protected route receives: a 401 and a code the front translates —
 * never the sentence of a library.
 */
final class ApiEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse(['error' => 'error.auth.required'], Response::HTTP_UNAUTHORIZED);
    }
}
