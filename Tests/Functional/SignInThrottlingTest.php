<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Functional;

use Jul6Art\AuthBundle\DependencyInjection\AuthExtension;
use Jul6Art\AuthBundle\Security\SignInRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The limiter as an application gets it: compiled by FrameworkBundle, counting in the bundle's own
 * filesystem pool.
 */
#[CoversClass(AuthExtension::class)]
#[CoversClass(SignInRateLimiter::class)]
final class SignInThrottlingTest extends AbstractFunctionalTestCase
{
    public function testTheCompiledLimiterCountsTheSignInPathsOnly(): void
    {
        $container = $this->boot(authConfig: ['sign_in_throttling' => ['paths' => ['/api/auth/']]]);

        // The pool is a file on disk, kept from one run to the next: start from an empty one.
        $pool = $container->get('auth.sign_in_throttling.cache');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        self::assertTrue($pool->clear());

        $limiter = $container->get('auth.sign_in_throttling.limiter');
        self::assertInstanceOf(SignInRateLimiter::class, $limiter);

        $attempt = Request::create('/api/auth/login', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $attempt->attributes->set(SecurityRequestAttributes::LAST_USERNAME, 'ann@example.test');
        for ($i = 0; $i < 5; ++$i) {
            self::assertTrue($limiter->consume($attempt)->isAccepted());
        }
        self::assertFalse($limiter->consume($attempt)->isAccepted());

        self::assertTrue($limiter->consume(Request::create('/api/reports', server: ['REMOTE_ADDR' => '10.0.0.1']))->isAccepted());
    }
}
