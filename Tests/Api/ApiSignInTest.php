<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuthBundle\Api\Security\RealmResetPasswordRequests;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\Account;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\LoginRecord;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\RefreshToken;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA\ResetPasswordRequest;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmB;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The API sign-in, end to end, in a kernel that serves two applications: realm A on `a.test`, realm B on
 * `b.test`, each with its accounts in its own base. What one realm hands out — a refresh token, a reset link —
 * never opens the other, even for an account that shares the address.
 */
#[CoversNothing]
final class ApiSignInTest extends WebTestCase
{
    private const string EMAIL = 'someone@example.test';

    private const string PASSWORD = 'a passphrase long enough';

    private KernelBrowser $client;

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testThePasswordAloneSignsInWhereTheSecondFactorIsOff(): void
    {
        $this->start(twoFactorRequired: false);

        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'status.signed_in'], $this->body());
        self::assertNotNull($this->cookie('__Host-access_token'));
        self::assertNotNull($this->cookie('refresh_token'));
        self::assertSame(1, $this->rows('a', LoginRecord::class));
        self::assertSame(0, $this->rows('b', RealmB\LoginRecord::class));
    }

    public function testAWrongPasswordSaysNothingOfTheAccount(): void
    {
        $this->start(twoFactorRequired: false);

        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => 'not the passphrase']);
        self::assertResponseStatusCodeSame(401);
        $wrongPassword = $this->body();

        $this->json('a', 'POST', '/api/login', ['email' => 'nobody@example.test', 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(401);
        self::assertSame($wrongPassword, $this->body());
        self::assertSame(['error' => 'error.login.invalid_credentials'], $wrongPassword);
    }

    public function testARefreshTokenOpensOnlyItsOwnRealm(): void
    {
        $this->start(twoFactorRequired: false);
        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $refreshToken = $this->cookie('refresh_token');
        self::assertNotNull($refreshToken);

        // Carried to realm B, where an account has the same address: refused.
        $this->carry('b', $refreshToken);
        $this->client->request('POST', 'https://b.test/api/token/refresh');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'error.auth.session_expired'], $this->body());

        $this->carry('a', $refreshToken);
        $this->client->request('POST', 'https://a.test/api/token/refresh');
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->cookie('__Host-access_token'));
    }

    public function testTheFirstSignInEnrolsTheSecondFactorUnderTheRealmsName(): void
    {
        $this->start(twoFactorRequired: true);

        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertSame(['status' => 'status.two_factor.enrolment_required'], $this->body());
        self::assertNull($this->cookie('__Host-access_token'));

        $this->client->request('GET', 'https://a.test/api/enrolment');
        self::assertResponseIsSuccessful();
        $enrolment = $this->body();
        self::assertIsString($enrolment['secret'] ?? null);
        self::assertIsString($enrolment['qrCode'] ?? null);
        self::assertStringStartsWith('data:image/svg+xml', $enrolment['qrCode']);

        $secret = str_replace(' ', '', $enrolment['secret']);
        self::assertNotSame('', $secret);
        $this->json('a', 'POST', '/api/enrolment', ['code' => '000000']);
        self::assertResponseStatusCodeSame(422);

        $this->json('a', 'POST', '/api/enrolment', ['code' => TOTP::createFromSecret($secret, new NativeClock())->now()]);
        self::assertResponseIsSuccessful();
        $codes = $this->body()['backupCodes'] ?? null;
        self::assertIsArray($codes);
        self::assertCount(10, $codes);
        self::assertNotNull($this->cookie('__Host-access_token'));

        // The next sign-in owes the second factor; a backup code passes it once.
        $this->client->getCookieJar()->clear();
        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertSame(['status' => 'status.two_factor.required'], $this->body());
        $this->json('a', 'POST', '/api/2fa_check', ['code' => $codes[0]]);
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'status.signed_in'], $this->body());
    }

    public function testAResetLinkIsTheRealmsOwnAndOpensNothingElsewhere(): void
    {
        $this->start(twoFactorRequired: false);

        $this->json('a', 'POST', '/api/password/forgot', ['email' => self::EMAIL]);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('no-reply@a.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Realm A', $email->getFrom()[0]->getName());
        self::assertSame(1, preg_match('#https://a\.test/password/([^\s"<]+)#', (string) $email->getTextBody(), $match));
        $token = rawurldecode($match[1]);

        // An unknown address gets the same answer, and no e-mail (the count is the request's).
        $this->json('a', 'POST', '/api/password/forgot', ['email' => 'nobody@example.test']);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(0);

        $this->json('b', 'POST', '/api/password/reset', ['token' => $token, 'password' => 'another long passphrase']);
        self::assertResponseStatusCodeSame(422);

        $this->json('a', 'POST', '/api/password/reset', ['token' => $token, 'password' => 'another long passphrase']);
        self::assertResponseStatusCodeSame(204);

        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => 'another long passphrase']);
        self::assertResponseIsSuccessful();
        // Realm B's account, at the same address, keeps its password.
        $this->json('b', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testAnAccountsResetRequestsLeaveWithItInItsRealmOnly(): void
    {
        $this->start(twoFactorRequired: false);

        $this->json('a', 'POST', '/api/password/forgot', ['email' => self::EMAIL]);
        $this->json('b', 'POST', '/api/password/forgot', ['email' => self::EMAIL]);
        self::assertSame(1, $this->rows('a', ResetPasswordRequest::class));
        self::assertSame(1, $this->rows('b', RealmB\ResetPasswordRequest::class));

        // Outside any request — a console command that deletes the account: its realm is the account's.
        $account = $this->manager('a')->getRepository(Account::class)->findOneBy(['email' => self::EMAIL]);
        self::assertInstanceOf(Account::class, $account);
        $requests = self::getContainer()->get(RealmResetPasswordRequests::class);
        self::assertInstanceOf(RealmResetPasswordRequests::class, $requests);
        $requests->removeRequests($account);

        self::assertSame(0, $this->rows('a', ResetPasswordRequest::class));
        // Realm B's account, at the same address, keeps its own.
        self::assertSame(1, $this->rows('b', RealmB\ResetPasswordRequest::class));
    }

    public function testTheLogoutClearsBothCookiesAndRevokesTheRefreshToken(): void
    {
        $this->start(twoFactorRequired: false);
        $this->json('a', 'POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        $refreshToken = $this->cookie('refresh_token');
        self::assertSame(1, $this->rows('a', RefreshToken::class));

        $this->client->request('POST', 'https://a.test/api/logout');
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->rows('a', RefreshToken::class));

        self::assertNotNull($refreshToken);
        $this->carry('a', $refreshToken);
        $this->client->request('POST', 'https://a.test/api/token/refresh');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'error.auth.session_expired'], $this->body());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ApiTestKernel(true === ($options['two_factor_required'] ?? false));
    }

    /**
     * A fresh pair of bases, with the same address in both realms and the same password.
     */
    private function start(bool $twoFactorRequired): void
    {
        $this->client = self::createClient(['two_factor_required' => $twoFactorRequired]);
        $this->client->disableReboot();

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        foreach (['a' => new Account(self::EMAIL, 'Someone'), 'b' => new RealmB\Account(self::EMAIL, 'Someone')] as $realm => $account) {
            $manager = $this->manager($realm);
            $tool = new SchemaTool($manager);
            $metadata = $manager->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
            $account->setPassword($hasher->hashPassword($account, self::PASSWORD));
            $manager->persist($account);
            $manager->flush();
            $manager->clear();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(string $realm, string $method, string $path, array $payload): void
    {
        $this->client->request($method, 'https://'.$realm.'.test'.$path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    private function body(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($body) ? $body : [];
    }

    /**
     * The refresh cookie, alone in the jar, on the realm's host: as a browser — or a thief — would send it.
     */
    private function carry(string $realm, string $refreshToken): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set(new Cookie('refresh_token', $refreshToken, null, '/api', $realm.'.test', true, true));
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
    private function rows(string $realm, string $class): int
    {
        $manager = $this->manager($realm);
        $manager->clear();

        return $manager->getRepository($class)->count([]);
    }

    private function manager(string $realm): EntityManagerInterface
    {
        $manager = self::getContainer()->get('doctrine.orm.'.$realm.'_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
