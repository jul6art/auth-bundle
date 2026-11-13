<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Jul6Art\AuthBundle\Account\Event\AccountCreationEvent;
use Jul6Art\CoreBundle\Util\Strings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates an account from the console — the first super administrator, typically: on a server, it is how the first
 * account is born, the fixtures never going there.
 *
 * ```
 * bin/console app:user:create ada@example.test --admin --first-name=Ada --last-name=Lovelace
 * bin/console app:user:create ada@example.test --set username=ada
 * ```
 *
 * - **The password is asked HIDDEN**, never taken as an argument: an argument lands in the shell's history, in
 *   `ps`, and in the orchestrator's logs.
 * - **The account is the application's** (`auth.account.user_class`), written through its own setters:
 *   `setEmail()`, `setRoles()`, `setPassword()`, and `setFirstName()`, `setLastName()`, `setIsActive(true)` when it
 *   has them. `--set <property>=<value>` calls one more setter — the username of an application that has one.
 * - **The address is looked up normalised** (lower case, trimmed), as the entities store it: `Ada@x.test` finds
 *   the account `ada@x.test` already holds.
 * - {@see AccountCreationEvent} lets the application complete the account in the same flush.
 *
 * No lock: the command is never scheduled, it is run by hand.
 */
#[AsCommand(name: 'app:user:create', description: 'Creates an account')]
final class CreateUserCommand extends Command
{
    /**
     * @param class-string<PasswordAuthenticatedUserInterface> $userClass
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $userClass,
        private readonly string $adminRole,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The account\'s address')
            ->addOption('admin', null, InputOption::VALUE_NONE, \sprintf('Gives %s', $this->adminRole))
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'First name', '')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Last name', '')
            ->addOption('set', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Another property, "<property>=<value>", written by its setter');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = Strings::lowerEmail((string) $input->getArgument('email')) ?? '';
        if ('' === $email) {
            $io->error('The address is empty.');

            return Command::FAILURE;
        }

        $manager = $this->manager();
        if (null !== $manager->getRepository($this->userClass)->findOneBy(['email' => $email])) {
            $io->error(\sprintf('An account already exists for "%s".', $email));

            return Command::FAILURE;
        }

        $setters = $this->setters($input);
        if (\is_string($setters)) {
            $io->error($setters);

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password');
        if (!\is_string($password) || '' === $password) {
            $io->error('Empty password.');

            return Command::FAILURE;
        }

        $account = new ($this->userClass)();
        // Each setter was checked on the class by setters(), before the password was asked.
        foreach ($setters as $method => $value) {
            $account->{$method}($value);
        }

        if (!method_exists($account, 'setPassword')) {
            throw new \LogicException(\sprintf('%s has no setPassword().', $this->userClass));
        }

        $account->setPassword($this->hasher->hashPassword($account, $password));
        $this->dispatcher->dispatch(new AccountCreationEvent($account, true === $input->getOption('admin')));

        $manager->persist($account);
        $manager->flush();

        $io->success(\sprintf('Account "%s" created.', $email));

        return Command::SUCCESS;
    }

    /**
     * The setters to call, by name, with their value — or what is wrong with the options.
     *
     * @return array<string, string|bool|list<string>>|string
     */
    private function setters(InputInterface $input): array|string
    {
        foreach (['setEmail', 'setPassword'] as $setter) {
            if (!method_exists($this->userClass, $setter)) {
                return \sprintf('%s has no %s(): it cannot be created here.', $this->userClass, $setter);
            }
        }

        $setters = ['setEmail' => Strings::lowerEmail((string) $input->getArgument('email')) ?? ''];
        if (true === $input->getOption('admin')) {
            if (!method_exists($this->userClass, 'setRoles')) {
                return \sprintf('%s has no setRoles(): --admin cannot be written.', $this->userClass);
            }

            $setters['setRoles'] = [$this->adminRole];
        }

        // The names, when the account has them: an option left empty writes an empty name, as before the bundle.
        foreach (['first-name' => 'setFirstName', 'last-name' => 'setLastName'] as $option => $setter) {
            $value = (string) $input->getOption($option);
            if (method_exists($this->userClass, $setter)) {
                $setters[$setter] = $value;
            } elseif ('' !== $value) {
                return \sprintf('%s has no %s(): --%s cannot be written.', $this->userClass, $setter, $option);
            }
        }

        // An account created by hand is active: there is no one to validate it.
        if (method_exists($this->userClass, 'setIsActive')) {
            $setters['setIsActive'] = true;
        }

        $extra = $input->getOption('set');
        foreach (\is_array($extra) ? $extra : [] as $assignment) {
            $parts = explode('=', (string) $assignment, 2);
            if (2 !== \count($parts) || '' === trim($parts[0])) {
                return \sprintf('--set "%s": expected "<property>=<value>".', (string) $assignment);
            }

            $setter = 'set'.ucfirst(trim($parts[0]));
            if (!method_exists($this->userClass, $setter)) {
                return \sprintf('%s has no %s(): --set %s cannot be written.', $this->userClass, $setter, trim($parts[0]));
            }

            $setters[$setter] = $parts[1];
        }

        return $setters;
    }

    private function manager(): ObjectManager
    {
        return $this->registry->getManagerForClass($this->userClass)
            ?? throw new \LogicException(\sprintf('No entity manager maps "%s" (auth.account.user_class).', $this->userClass));
    }
}
