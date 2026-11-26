<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\OidcProviderDouble;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\LoginRecord;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The sign-in through an OpenID provider, end to end: realm A on `a.test` enables it, realm B does not. The provider
 * checks who signs in; the realm finds the account of the same address and opens its own session — the same cookies as
 * its own sign-in, its second factor not asked again since the provider checked one. Everything that does not hold is
 * refused back to the sign-in page, in a code it translates.
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
        return new ApiTestKernel(true === ($options['two_factor_required'] ?? false));
    }

    public function testAnAccountSignsInThroughTheProviderWithoutItsSecondFactorAgain(): void
    {
        $this->start();
        [$query, $location] = $this->authorization();
        self::assertStringStartsWith('https://console.test/oidc?', $location);
        self::assertSame(['code', OidcProviderDouble::CLIENT, 'https://a.test/api/auth/oidc/callback', 'S256'], [$query['response_type'], $query['client_id'], $query['redirect_uri'], $query['code_challenge_method']]);

        $this->back($query, ['nonce' => $query['nonce'], 'email' => self::EMAIL, 'email_verified' => true, 'amr' => ['pwd', 'otp']]);

        self::assertResponseRedirects('/');
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

    private function start(): void
    {
        OidcProviderDouble::forget();
        $this->client = self::createClient(['two_factor_required' => true]);
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
    private function authorization(): array
    {
        $this->client->request('GET', 'https://a.test/api/auth/oidc/start');
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
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($name === $cookie->getName() && !$cookie->isCleared() && null !== $cookie->getValue()) {
                return $cookie->getValue();
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
