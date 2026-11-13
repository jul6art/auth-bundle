<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Account;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuthBundle\Account\Command\CreateUserCommand;
use Jul6Art\AuthBundle\Account\Command\DevLoginLinkCommand;
use Jul6Art\AuthBundle\Account\Dev\LoginLinkController;
use Jul6Art\AuthBundle\Account\Event\AccountCreationEvent;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Tests\Account\Fixtures\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The account commands of T20 lot 3: `app:user:create` on the application's entity, `app:dev:login-link` in the
 * "dev" environment only.
 */
#[CoversClass(CreateUserCommand::class)]
#[CoversClass(DevLoginLinkCommand::class)]
#[CoversClass(LoginLinkController::class)]
final class AccountCommandsTest extends TestCase
{
    private const string PASSWORD = 'a passphrase long enough';

    private ?AccountTestKernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;

        $handler = set_exception_handler(null);
        restore_exception_handler();
        if (\is_array($handler) && $handler[0] instanceof ErrorHandler) {
            restore_exception_handler();
        }

        parent::tearDown();
    }

    public function testCreateUserWritesTheApplicationsAccountThroughItsSetters(): void
    {
        $tester = $this->command('test', ['user_class' => Member::class, 'create_user' => true], 'app:user:create');
        $tester->setInputs([self::PASSWORD]);

        $status = $tester->execute([
            'email' => '  Ada@Example.TEST ',
            '--admin' => true,
            '--first-name' => 'Ada',
            '--last-name' => 'Lovelace',
            '--set' => ['username=ada'],
        ]);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        $member = $this->member('ada@example.test');
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $member->getRoles());
        self::assertSame('Ada', $member->getFirstName());
        self::assertSame('Lovelace', $member->getLastName());
        self::assertSame('ada', $member->getUsername());
        self::assertTrue($member->isActive(), 'An account created by hand is active: there is no one to validate it.');
        self::assertSame(self::PASSWORD, $member->getPassword(), 'The password is hashed by the application\'s hasher (plaintext in this test).');
    }

    public function testTheAdminRoleIsTheConfigurationsAndAnAccountWithoutItHasNone(): void
    {
        $tester = $this->command('test', ['user_class' => Member::class, 'create_user' => ['admin_role' => 'ROLE_ADMIN']], 'app:user:create');
        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'grace@example.test', '--admin' => true]));
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->member('grace@example.test')->getRoles());

        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'alan@example.test']));
        self::assertSame(['ROLE_USER'], $this->member('alan@example.test')->getRoles());
    }

    public function testAListenerCompletesTheAccountInTheSameFlush(): void
    {
        $tester = $this->command('test', ['user_class' => Member::class, 'create_user' => true], 'app:user:create');
        $dispatcher = $this->kernel()->getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $seen = [];
        $dispatcher->addListener(AccountCreationEvent::class, static function (AccountCreationEvent $event) use (&$seen): void {
            self::assertInstanceOf(Member::class, $event->account);
            self::assertNull($event->account->getId(), 'The listener runs before the account is written.');
            $seen[] = $event->admin;
            $event->account->confirmBy('the listener');
        });

        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'ada@example.test', '--admin' => true]));

        self::assertSame([true], $seen);
        $this->entityManager()->clear();
        self::assertSame('the listener', $this->member('ada@example.test')->getConfirmedBy());
    }

    public function testAnAddressAlreadyTakenIsRefusedWhateverItsCase(): void
    {
        $tester = $this->command('test', ['user_class' => Member::class, 'create_user' => true], 'app:user:create');
        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'ada@example.test']));

        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'ADA@example.test']));
        self::assertStringContainsString('An account already exists for "ada@example.test"', $tester->getDisplay());
        self::assertCount(1, $this->entityManager()->getRepository(Member::class)->findAll());
    }

    public function testWhatTheAccountCannotHoldIsRefusedBeforeThePasswordIsAsked(): void
    {
        $tester = $this->command('test', ['user_class' => Member::class, 'create_user' => true], 'app:user:create');
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'ada@example.test', '--set' => ['nickname=ada']]));
        self::assertStringContainsString('has no setNickname()', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'ada@example.test', '--set' => ['username']]));
        self::assertStringContainsString('expected "<property>=<value>"', $tester->getDisplay());

        $tester->setInputs(['']);
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'ada@example.test']));
        self::assertStringContainsString('Empty password', $tester->getDisplay());

        self::assertSame([], $this->entityManager()->getRepository(Member::class)->findAll());
    }

    public function testTheBundlesOwnUserHasNoNamesToWrite(): void
    {
        $tester = $this->command('test', ['create_user' => true], 'app:user:create', User::class);
        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'ada@example.test', '--first-name' => 'Ada']));
        self::assertStringContainsString('has no setFirstName()', $tester->getDisplay());

        $tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'ada@example.test']), $tester->getDisplay());
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'ada@example.test']);
        self::assertInstanceOf(User::class, $user);
    }

    public function testTheCommandsAreOffByDefault(): void
    {
        $this->kernel = new AccountTestKernel('dev', ['user_class' => Member::class]);
        $this->kernel->boot();
        $application = new Application($this->kernel);

        self::assertFalse($application->has('app:user:create'));
        self::assertFalse($application->has('app:dev:login-link'));
    }

    public function testTheDevelopmentLinkExistsInTheDevEnvironmentOnly(): void
    {
        $this->kernel = new AccountTestKernel('test', ['user_class' => Member::class, 'dev_login_link' => true]);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        self::assertFalse($container->has(DevLoginLinkCommand::class), 'A link that signs in on a signature has nothing to do outside "dev".');
        self::assertFalse($container->has(LoginLinkController::class));
        self::assertFalse(new Application($this->kernel)->has('app:dev:login-link'));
    }

    public function testTheDevelopmentLinkFindsTheAccountByItsNormalisedAddress(): void
    {
        $tester = $this->command('dev', ['user_class' => Member::class, 'dev_login_link' => true], 'app:dev:login-link');
        $member = new Member()->setEmail('ada@example.test')->setPassword('hash');
        $this->entityManager()->persist($member);
        $this->entityManager()->flush();

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'ADA@example.test']), $tester->getDisplay());
        self::assertMatchesRegularExpression('#/login-link/check\?user=ada@example\.test&expires=\d+&hash=#', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.test']));
        self::assertStringContainsString('No account with the address "nobody@example.test"', $tester->getDisplay());
    }

    public function testTheTargetOfTheLinkIsNeverRun(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('intercepted by the login_link authenticator');

        new LoginLinkController()();
    }

    /**
     * @param array<string, mixed> $account
     * @param class-string         $entity  the class whose table the test needs
     */
    private function command(string $environment, array $account, string $name, string $entity = Member::class): CommandTester
    {
        $this->kernel = new AccountTestKernel($environment, $account);
        $this->kernel->boot();

        new SchemaTool($this->entityManager())->createSchema([$this->entityManager()->getClassMetadata($entity)]);

        return new CommandTester(new Application($this->kernel)->find($name));
    }

    private function kernel(): AccountTestKernel
    {
        return $this->kernel ?? throw new \LogicException('No kernel booted.');
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = $this->kernel()->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function member(string $email): Member
    {
        $member = $this->entityManager()->getRepository(Member::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Member::class, $member, \sprintf('No account "%s" was written.', $email));

        return $member;
    }
}
