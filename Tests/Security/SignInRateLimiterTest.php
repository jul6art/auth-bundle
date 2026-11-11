<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Security;

use Jul6Art\AuthBundle\Security\SignInRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

#[CoversClass(SignInRateLimiter::class)]
final class SignInRateLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    #[\Override]
    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    /**
     * The defect it exists for: on a firewall that also reads a token, a failed token spent the
     * IP's budget, and a few dozen forged tokens locked every valid one out of the API.
     */
    public function testARequestOutsideTheSignInPathsIsNeverCountedNorRefused(): void
    {
        $limiter = $this->limiter();
        $request = self::request('/api/reports', null);

        for ($i = 0; $i < 100; ++$i) {
            self::assertTrue($limiter->consume($request)->isAccepted());
        }

        self::assertTrue($limiter->peek(self::request('/api/auth/login', 'ann@example.test'))->isAccepted());
        self::assertSame(5, $limiter->peek(self::request('/api/auth/login', 'ann@example.test'))->getRemainingTokens());
    }

    public function testOneAccountGetsMaxAttemptsFromOneIp(): void
    {
        $limiter = $this->limiter();
        $request = self::request('/api/auth/login', 'ann@example.test');

        for ($i = 0; $i < 5; ++$i) {
            self::assertTrue($limiter->consume($request)->isAccepted(), 'Attempt '.($i + 1));
        }

        self::assertFalse($limiter->consume($request)->isAccepted());
        // Another account from the same IP still has its own budget.
        self::assertTrue($limiter->consume(self::request('/api/auth/login', 'bob@example.test'))->isAccepted());
    }

    public function testTheIdentifierIsCaseInsensitive(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 5; ++$i) {
            $limiter->consume(self::request('/api/auth/login', 0 === $i % 2 ? 'Ann@Example.test' : 'ann@example.test'));
        }

        self::assertFalse($limiter->consume(self::request('/api/auth/login', 'ANN@EXAMPLE.TEST'))->isAccepted());
    }

    /**
     * The sweep: one address per request never trips a per-account budget, the IP's stops it.
     */
    public function testTheIpGetsFiveTimesAsManyAttemptsEveryIdentifierTogether(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 25; ++$i) {
            self::assertTrue($limiter->consume(self::request('/api/auth/login', \sprintf('user%d@example.test', $i)))->isAccepted());
        }

        self::assertFalse($limiter->consume(self::request('/api/auth/login', 'user99@example.test'))->isAccepted());
        self::assertTrue($limiter->consume(self::request('/api/auth/login', 'user99@example.test', '10.0.0.2'))->isAccepted(), 'Another IP is not concerned.');
    }

    /**
     * ⚠️ A success clears the account's budget, never the IP's: clearing both let a client that owns
     * one account reset its sweep between two volleys.
     */
    public function testASuccessfulSignInClearsTheAccountBudgetOnly(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 20; ++$i) {
            $limiter->consume(self::request('/api/auth/login', \sprintf('user%d@example.test', $i)));
        }
        for ($i = 0; $i < 4; ++$i) {
            $limiter->consume(self::request('/api/auth/login', 'mine@example.test'));
        }

        $limiter->reset(self::request('/api/auth/login', 'mine@example.test'));

        self::assertSame(5, $limiter->peek(self::request('/api/auth/login', 'other@example.test', '10.0.0.9'))->getRemainingTokens());
        self::assertTrue($limiter->consume(self::request('/api/auth/login', 'mine@example.test'))->isAccepted(), '24th attempt of the IP.');
        self::assertFalse($limiter->consume(self::request('/api/auth/login', 'mine@example.test'))->isAccepted(), 'The 26th is refused: the IP budget survived the success.');
    }

    public function testEveryConfiguredPathCounts(): void
    {
        $limiter = $this->limiter(['/api/auth/', '/login']);

        for ($i = 0; $i < 3; ++$i) {
            $limiter->consume(self::request('/api/auth/refresh', 'ann@example.test'));
            $limiter->consume(self::request('/login', 'ann@example.test'));
        }

        // What Symfony's listener checks: a peek consumes nothing, so an empty budget reads as 0 left.
        self::assertSame(0, $limiter->peek(self::request('/login', 'ann@example.test'))->getRemainingTokens(), 'The web form and the API share one budget.');
    }

    /**
     * The cache holds no address in the clear: the keys are hashed with the secret.
     */
    public function testTheKeysHoldNoIdentifierNorIpInTheClear(): void
    {
        $spy = new class implements RateLimiterFactoryInterface {
            /** @var list<string> */
            public array $keys = [];

            #[\Override]
            public function create(?string $key = null): LimiterInterface
            {
                $this->keys[] = (string) $key;

                return new RateLimiterFactory(['id' => 'spy', 'policy' => 'no_limit'], new InMemoryStorage())->create($key);
            }
        };

        new SignInRateLimiter($spy, $spy, ['/api/auth/'], 'secret')->consume(self::request('/api/auth/login', 'ann@example.test'));

        self::assertCount(2, $spy->keys);
        foreach ($spy->keys as $key) {
            self::assertStringNotContainsString('ann', $key);
            self::assertStringNotContainsString('10.0.0.1', $key);
        }
    }

    public function testItRefusesToCountNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->limiter([]);
    }

    /**
     * @param list<string> $paths
     */
    private function limiter(array $paths = ['/api/auth/']): SignInRateLimiter
    {
        $factory = fn (string $scope, int $limit): RateLimiterFactory => new RateLimiterFactory(
            ['id' => 'test_'.$scope, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'],
            $this->storage,
        );

        return new SignInRateLimiter($factory('global', 25), $factory('local', 5), $paths, 'secret');
    }

    private static function request(string $path, ?string $identifier, string $ip = '10.0.0.1'): Request
    {
        $request = Request::create($path, 'POST', server: ['REMOTE_ADDR' => $ip]);
        if (null !== $identifier) {
            $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);
        }

        return $request;
    }
}
