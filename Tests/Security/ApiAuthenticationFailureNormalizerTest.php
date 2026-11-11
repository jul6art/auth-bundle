<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Security;

use Jul6Art\AuthBundle\Security\ApiAuthenticationFailureNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[CoversClass(ApiAuthenticationFailureNormalizer::class)]
final class ApiAuthenticationFailureNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{AuthenticationException}>
     */
    public static function failures(): iterable
    {
        yield 'wrong password' => [new BadCredentialsException('The presented password is invalid.')];
        yield 'unknown account' => [new UserNotFoundException('No account for this address.')];
        yield 'disabled account' => [new CustomUserMessageAccountStatusException('Account disabled.')];
        yield 'refresh token that never existed' => [new AuthenticationException('JWT Refresh Token Not Found')];
        yield 'expired token' => [new AuthenticationException('Expired JWT Token')];
    }

    /**
     * The oracle it closes: each authenticator told why it failed.
     */
    #[DataProvider('failures')]
    public function testEveryFailureAnswersTheSameUniform401(AuthenticationException $failure): void
    {
        $response = $this->normalise($failure);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(['code' => 401, 'errorCode' => 'INVALID_CREDENTIALS', 'message' => 'Invalid credentials.'], self::body($response));
    }

    public function testTheLimiterRefusalIsA429WithRetryAfter(): void
    {
        $response = $this->normalise(new TooManyLoginAttemptsAuthenticationException(2));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('120', $response->headers->get('Retry-After'));
        self::assertSame(['code' => 429, 'errorCode' => 'RATE_LIMITED', 'message' => 'Too many attempts. Try again later.'], self::body($response));
    }

    public function testARefusalWithoutAWindowSaysAMinute(): void
    {
        self::assertSame('60', $this->normalise(new TooManyLoginAttemptsAuthenticationException())->headers->get('Retry-After'));
    }

    public function testTheCodesAreTheApplications(): void
    {
        $normalizer = new ApiAuthenticationFailureNormalizer(['api'], 'BAD_LOGIN', 'SLOW_DOWN');

        self::assertSame('BAD_LOGIN', self::body($this->normalise(new BadCredentialsException(), normalizer: $normalizer))['errorCode']);
        self::assertSame('SLOW_DOWN', self::body($this->normalise(new TooManyLoginAttemptsAuthenticationException(1), normalizer: $normalizer))['errorCode']);
    }

    /**
     * A form login redirects and flashes its own message: a JSON body would break its screen.
     */
    public function testAnotherFirewallKeepsItsOwnResponse(): void
    {
        $original = new Response('', Response::HTTP_FOUND);
        $event = $this->event(new BadCredentialsException(), 'main', $original);

        new ApiAuthenticationFailureNormalizer(['api'], 'INVALID_CREDENTIALS', 'RATE_LIMITED')($event);

        self::assertSame($original, $event->getResponse());
    }

    private function normalise(AuthenticationException $failure, string $firewall = 'api', ?ApiAuthenticationFailureNormalizer $normalizer = null): Response
    {
        $event = $this->event($failure, $firewall, new JsonResponse(['code' => 401, 'message' => $failure->getMessage()], Response::HTTP_UNAUTHORIZED));
        ($normalizer ?? new ApiAuthenticationFailureNormalizer(['api'], 'INVALID_CREDENTIALS', 'RATE_LIMITED'))($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    private function event(AuthenticationException $failure, string $firewall, Response $response): LoginFailureEvent
    {
        return new LoginFailureEvent($failure, self::createStub(AuthenticatorInterface::class), Request::create('/api/auth/login', 'POST'), $response, $firewall);
    }

    /**
     * @return array<mixed>
     */
    private static function body(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }
}
