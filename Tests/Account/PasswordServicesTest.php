<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Account;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Jul6Art\AuthBundle\Account\PasswordResetEmail;
use Jul6Art\AuthBundle\Account\PasswordResetMailer;
use Jul6Art\AuthBundle\Account\PasswordWriter;
use Jul6Art\AuthBundle\Tests\Account\Fixtures\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The two services the API and admin-bundle's web screens share (T20 lot 3).
 */
#[CoversClass(PasswordWriter::class)]
#[CoversClass(PasswordResetMailer::class)]
#[CoversClass(PasswordResetEmail::class)]
final class PasswordServicesTest extends TestCase
{
    public function testTheWriterHashesWritesAndEndsTheApiSessions(): void
    {
        $member = new Member()->setEmail('ada@example.test');
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('persist')->with($member);
        $manager->expects(self::once())->method('flush');
        $refreshTokens = $this->createMock(RevokeRefreshTokenManagerInterface::class);
        $refreshTokens->expects(self::once())->method('revokeAllForUser')->with($member);

        new PasswordWriter($this->hasher(), $this->registry($manager), $refreshTokens)->write($member, 'new secret');

        self::assertSame('hashed:new secret', $member->getPassword());
    }

    public function testWithoutTheApiThereIsNoSessionToEnd(): void
    {
        $member = new Member();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('flush');

        new PasswordWriter($this->hasher(), $this->registry($manager))->write($member, 'new secret');

        self::assertSame('hashed:new secret', $member->getPassword());
    }

    public function testAnAccountWithoutSetterIsRefusedBeforeAnythingIsWritten(): void
    {
        $account = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): string
            {
                return '';
            }
        };
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('has no setPassword()');

        new PasswordWriter($this->hasher(), $this->registry($manager))->write($account, 'new secret');
    }

    public function testTheEmailIsTranslatedInItsOwnLanguageNotTheRequests(): void
    {
        $mailer = new class implements MailerInterface {
            public ?RawMessage $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')
            ->with('security.reset_password.email.subject', ['%app%' => 'Demo'], 'security', 'nl')
            ->willReturn('Wachtwoord opnieuw instellen');

        new PasswordResetMailer($mailer, $translator)->deliver(new PasswordResetEmail(
            from: new Address('noreply@example.test'),
            to: new Address('ada@example.test'),
            subject: 'security.reset_password.email.subject',
            translationDomain: 'security',
            htmlTemplate: 'security/reset_password_email.html.twig',
            context: ['space' => 'portal'],
            locale: 'nl',
            subjectParameters: ['%app%' => 'Demo'],
        ));

        $email = $mailer->sent;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('Wachtwoord opnieuw instellen', $email->getSubject());
        self::assertSame('nl', $email->getLocale());
        self::assertSame('security/reset_password_email.html.twig', $email->getHtmlTemplate());
        self::assertNull($email->getTextTemplate());
        self::assertSame(['space' => 'portal'], $email->getContext());
        self::assertSame('ada@example.test', $email->getTo()[0]->getAddress());
    }

    /**
     * The old names are declared by class_alias() only, when the autoloader reaches them.
     *
     * @param class-string $moved
     */
    #[DataProvider('movedServices')]
    public function testTheOldNamesAreAliasesUntilTheNextMajor(string $old, string $moved): void
    {
        self::assertTrue(class_exists($old), $old);
        self::assertSame($moved, new \ReflectionClass($old)->getName());
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function movedServices(): iterable
    {
        yield 'PasswordWriter' => ['Jul6Art\\AuthBundle\\Api\\Service\\PasswordWriter', PasswordWriter::class];
        yield 'PasswordResetMailer' => ['Jul6Art\\AuthBundle\\Api\\Service\\PasswordResetMailer', PasswordResetMailer::class];
    }

    private function hasher(): UserPasswordHasherInterface
    {
        return new class implements UserPasswordHasherInterface {
            public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
            {
                return 'hashed:'.$plainPassword;
            }

            public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
            {
                return true;
            }

            public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
            {
                return false;
            }
        };
    }

    private function registry(ObjectManager $manager): ManagerRegistry
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }
}
