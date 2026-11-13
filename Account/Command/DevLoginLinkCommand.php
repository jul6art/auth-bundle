<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account\Command;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\CoreBundle\Util\Strings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Makes a one-time sign-in URL for an account, to open a session on the local stack **without typing a password** —
 * to watch the screens with any account of the fixtures, another organisation's included. The URL is signed and
 * expires; it does not replace the sign-in, it spares typing credentials.
 *
 * ```
 * bin/console app:dev:login-link manager@example.test
 * ```
 *
 * ⚠️ **Registered in the "dev" environment only**, whatever the configuration says (`auth.account.dev_login_link`):
 * it needs the `login_link` handler of the firewall, which only exists where the application configures it — and a
 * URL that signs in on a signature has nothing to do anywhere else.
 *
 * The address is looked up normalised, as the entities store it: `Ada@x.test` finds `ada@x.test`.
 */
#[AsCommand(name: 'app:dev:login-link', description: 'Makes a sign-in URL without password (development only)')]
final class DevLoginLinkCommand extends Command
{
    /**
     * @param class-string<UserInterface> $userClass
     */
    public function __construct(
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly ManagerRegistry $registry,
        private readonly string $userClass,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The address of the account to open');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $account = $this->registry->getRepository($this->userClass)->findOneBy(['email' => Strings::lowerEmail($email) ?? '']);
        if (!$account instanceof UserInterface) {
            $style->error(\sprintf('No account with the address "%s".', $email));

            return Command::FAILURE;
        }

        // The URL alone on the output: it is copied, handed to `curl`, opened in a browser. The comments go to
        // STDERR through SymfonyStyle, which keeps `$(…)` usable.
        $style->success(\sprintf('Link for %s (expires as security.yaml says):', $email));
        $output->writeln($this->loginLinkHandler->createLoginLink($account)->getUrl());

        return Command::SUCCESS;
    }
}
