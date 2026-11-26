<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuthBundle\Api\Assistance\AssistanceEnded;
use Jul6Art\AuthBundle\Api\Assistance\Assistances;
use Jul6Art\AuthBundle\Api\Oidc\PendingSignIn;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\OidcClientResolverDouble;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\OidcProviderDouble;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\LoginRecord;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\RefreshToken;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The sign-in through an OpenID provider, end to end: realm A on `a.test` enables it, realm B does not. The provider
 * checks who signs in; the realm finds the account of the same address and opens its own session — the same cookies as
 * its own sign-in, its second factor not asked again since the provider checked one. Everything that does not hold is
 * refused back to the sign-in page, in a code it translates. What the sign-in keeps between its two steps is in a cookie
 * of its own, never in the session: a provider on another site sends the browser back without the session's cookie.
 */
#[CoversNothing]
final class OidcSignInTest extends WebTestCase
{
    private const string EMAIL = 'someone@example.test';

    private KernelBrowser $client;

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ApiTestKernel(true === ($options['two_factor_required'] ?? false), true === ($options['resolved'] ?? false));
    }

    public function testAnAccountSignsInThroughTheProviderWithoutItsSecondFactorAgain(): void
    {
        $this->start();
        [$query, $location] = $this->authorization();
        self::assertStringStartsWith('https://console.test/oidc?', $location);
        self::assertSame(['code', OidcProviderDouble::CLIENT, 'https://a.test/api/auth/oidc/callback', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['code_challenge_method']]);
        // Kept in a cookie of its own, which a browser sends back from another site — and no session started.
        $pending = $this->sent(PendingSignIn::COOKIE);
        self::assertNotNull($pending);
        self::assertSame([Cookie::SAMESITE_LAX, true, true, '/'], [$pending->getSameSite(), $pending->isSecure(), $pending->isHttpOnly(), $pending->getPath()]);
        self::assertSame([], array_filter($this->client->getCookieJar()->all(), static fn (BrowserCookie $cookie): bool => PendingSignIn::COOKIE !== $cookie->getName()));

        $this->back($query, ['nonce' => $query['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);

        self::assertResponseRedirects('/');
        self::assertNull($this->client->getCookieJar()->get(PendingSignIn::COOKIE, '/', 'a.test'), 'A sign-in comes back once.');
        self::assertNotNull($this->cookie('__Host-access_token'), 'Its own session, as after its own sign-in.');
        self::assertNotNull($this->cookie('refresh_token'));
        self::assertSame(1, $this->rows(LoginRecord::class), 'Recorded as any sign-in.');
        // The code exchanged with the verifier of its challenge, and the client's secret.
        $exchange = array_first(array_filter(OidcProviderDouble::$requests, static fn (array $request): bool => str_ends_with($request['url'], '/token'))) ?? null;
        self::assertNotNull($exchange);
        parse_str($exchange['body'], $sent);
        self::assertSame(['c-1', OidcProviderDouble::SECRET], [$sent['code'] ?? null, $sent['client_secret'] ?? null]);
        self::assertSame($query['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', \is_string($sent['code_verifier'] ?? null) ? $sent['code_verifier'] : '', true)), '+/', '-_'), '='));
        // The provider changed its pair under the same name: the keys kept an hour are read again, the next sign-in holds.
        OidcProviderDouble::rotate();
        $this->client->getCookieJar()->clear();
        [$again] = $this->authorization();
        $this->back($again, ['nonce' => $again['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);
        self::assertResponseRedirects('/');
        self::assertSame(2, $this->rows(LoginRecord::class));

        // "Stay signed in" unticked: the cookies die with the browser.
        $this->client->getCookieJar()->clear();
        [$unticked] = $this->authorization('?remember=0');
        $this->back($unticked, ['nonce' => $unticked['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);
        self::assertResponseRedirects('/');
        self::assertSame(0, $this->sent('__Host-access_token')?->getExpiresTime());
    }

    public function testSomeoneActingForTheAccountGetsAnAssistedSessionOfThirtyMinutesNeverRefreshed(): void
    {
        $this->start();
        // Its second factor on: its own session reaches its secrets — an assisted one, never.
        $manager = $this->manager();
        $account = $manager->getRepository(Account::class)->findOneBy(['email' => self::EMAIL]);
        self::assertInstanceOf(Account::class, $account);
        $account->startTotpEnrolment('JBSWY3DPEHPK3PXP')->confirmTotp(new \DateTimeImmutable());
        $manager->flush();
        $ended = [];
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(AssistanceEnded::class, static function (AssistanceEnded $event) use (&$ended): void {
            $ended[] = $event->assistance->assistant;
        });
        [$query] = $this->authorization();
        $this->back($query, ['nonce' => $query['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp'], 'act' => ['sub' => '7', 'email' => 'helper@console.test', 'name' => 'Helper']]);

        self::assertResponseRedirects('/');
        $access = $this->sent('__Host-access_token');
        self::assertNotNull($access);
        self::assertSame(0, $access->getExpiresTime(), 'Its cookie dies with the browser.');
        self::assertNull($this->sent('refresh_token'), 'Never refreshed: no refresh token leaves…');
        self::assertSame(0, $this->rows(RefreshToken::class), '… nor stays.');
        $payload = json_decode((string) base64_decode(strtr(explode('.', (string) $access->getValue())[1] ?? '', '-_', '+/'), true), true);
        self::assertIsArray($payload);
        self::assertSame(['email' => 'helper@console.test', 'name' => 'Helper'], $payload['act'] ?? null, 'Its access token names whoever acts…');
        self::assertEqualsWithDelta(time() + Assistances::TTL, $payload['exp'] ?? 0, 60, '… and ends with the assistance, thirty minutes on.');
        $manager->clear();
        self::assertSame(['helper@console.test'], array_map(static fn (LoginRecord $record): ?string => $record->getAssistedBy(), $manager->getRepository(LoginRecord::class)->findAll()), 'The sign-in recorded in both names.');

        // The application reads who acts; the account's own secrets stay out of reach.
        $this->client->request('GET', 'https://a.test/api/auth/assistance');
        self::assertResponseIsSuccessful();
        $assistance = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($assistance);
        self::assertSame(['email' => 'helper@console.test', 'name' => 'Helper'], $assistance['assistant'] ?? null);
        $this->client->request('POST', 'https://a.test/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: '{"currentPassword":"a","newPassword":"b"}');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', 'https://a.test/api/me/two-factor/setup');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', 'https://a.test/api/logout');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['helper@console.test'], $ended, 'Its end, dispatched.');

        // The account's own session: nobody acts for it, and its secrets are its own.
        $this->client->getCookieJar()->clear();
        [$own] = $this->authorization();
        $this->back($own, ['nonce' => $own['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);
        $this->client->request('GET', 'https://a.test/api/auth/assistance');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('POST', 'https://a.test/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: '{"currentPassword":"a","newPassword":"b"}');
        self::assertNotSame(403, $this->client->getResponse()->getStatusCode(), 'Its own session reaches its password (a wrong one, refused for that).');
    }

    public function testAPlatformTellsTheClientPerRequest(): void
    {
        $this->start(resolved: true);
        [$query] = $this->authorization();
        self::assertSame(OidcProviderDouble::CLIENT, $query['client_id']);
        $this->back($query, ['nonce' => $query['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);
        self::assertResponseRedirects('/');
        self::assertContains('a', OidcClientResolverDouble::$asked);

        // An application the platform does not know: no client, and the realm configures none — refused in words.
        OidcClientResolverDouble::$knows = false;
        $this->client->request('GET', 'https://a.test/api/auth/oidc/start');
        self::assertResponseRedirects('/login?error=auth.oidc.unavailable');
    }

    public function testWhatTheRealmRefuses(): void
    {
        $this->start();
        $good = ['email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']];
        $refusals = [
            'auth.oidc.denied' => [['error' => 'access_denied'], $good],
            'auth.oidc.factor' => [[], ['amr' => ['pwd']] + $good],
            'auth.oidc.no_account' => [[], ['email' => 'nobody@example.test'] + $good],
            'auth.oidc.invalid' => [[], ['aud' => 'b.prod'] + $good],
        ];
        foreach ($refusals as $error => [$query, $claims]) {
            [$authorization] = $this->authorization();
            $this->back($authorization + $query, ['nonce' => $authorization['nonce']] + $claims);
            self::assertResponseRedirects('/login?error='.$error, null, $error);
            self::assertNull($this->cookie('__Host-access_token'), $error);
        }

        // Another state than its own sign-in's: a forged answer.
        [$authorization] = $this->authorization();
        $this->back(['state' => 'not-its-state'] + $authorization, ['nonce' => $authorization['nonce']] + $good);
        self::assertResponseRedirects('/login?error=auth.oidc.invalid');

        // Its cookie altered, or none — gone with the sign-in's first answer.
        [$authorization] = $this->authorization();
        $jar = $this->client->getCookieJar();
        $kept = $jar->get(PendingSignIn::COOKIE, '/', 'a.test');
        self::assertNotNull($kept);
        $value = $kept->getValue();
        $jar->set(new BrowserCookie(PendingSignIn::COOKIE, substr($value, 0, -1).(str_ends_with($value, 'A') ? 'B' : 'A'), null, '/', 'a.test', true, true));
        $this->back($authorization, ['nonce' => $authorization['nonce']] + $good);
        self::assertResponseRedirects('/login?error=auth.oidc.invalid');
        $this->back($authorization, ['nonce' => $authorization['nonce']] + $good);
        self::assertResponseRedirects('/login?error=auth.oidc.invalid');

        // Signed by a key the provider does not publish; another nonce; the code refused.
        foreach ([['foreign' => true], ['nonce' => 'not-its-nonce'], ['status' => 400]] as $case) {
            [$authorization] = $this->authorization();
            OidcProviderDouble::$foreignKey = isset($case['foreign']);
            OidcProviderDouble::$tokenStatus = $case['status'] ?? 200;
            $this->back($authorization, ['nonce' => $case['nonce'] ?? $authorization['nonce']] + $good);
            self::assertResponseRedirects('/login?error=auth.oidc.invalid', null, (string) json_encode($case));
            OidcProviderDouble::forget();
        }
        self::assertSame(0, $this->rows(LoginRecord::class));

        // Realm B does not sign in through a provider.
        $this->client->request('GET', 'https://b.test/api/auth/oidc/start');
        self::assertResponseStatusCodeSame(404);
    }

    private function start(bool $resolved = false): void
    {
        OidcProviderDouble::forget();
        OidcClientResolverDouble::forget();
        $this->client = self::createClient(['two_factor_required' => true, 'resolved' => $resolved]);
        $this->client->disableReboot();
        // The provider's documents are kept an hour, in a pool that outlives the kernel: its double draws a new key each run.
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
        $manager = $this->manager();
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $manager->persist(new Account(self::EMAIL, 'Someone'));
        $manager->flush();
        $manager->clear();
    }

    /**
     * The start of a sign-in: the query the browser is sent to the provider with.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function authorization(string $query = ''): array
    {
        $this->client->request('GET', 'https://a.test/api/auth/oidc/start'.$query);
        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $parsed);
        $query = [];
        foreach ($parsed as $key => $value) {
            if (\is_string($value)) {
                $query[(string) $key] = $value;
            }
        }

        return [$query, $location];
    }

    /**
     * The provider sends the browser back, with a code — its next identity token saying what the test asks.
     *
     * @param array<string, string> $authorization
     * @param array<string, mixed>  $claims
     */
    private function back(array $authorization, array $claims): void
    {
        OidcProviderDouble::$claims = $claims;
        $query = ['code' => 'c-1', 'state' => $authorization['state'] ?? ''] + (isset($authorization['error']) ? ['error' => $authorization['error']] : []);
        $this->client->request('GET', 'https://a.test/api/auth/oidc/callback?'.http_build_query($query));
    }

    private function cookie(string $name): ?string
    {
        return $this->sent($name)?->getValue();
    }

    /**
     * A cookie the last response set — not one it removed.
     */
    private function sent(string $name): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($name === $cookie->getName() && !$cookie->isCleared() && null !== $cookie->getValue()) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function rows(string $class): int
    {
        $manager = $this->manager();
        $manager->clear();

        return $manager->getRepository($class)->count([]);
    }

    private function manager(): EntityManagerInterface
    {
        $manager = self::getContainer()->get('doctrine.orm.a_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
