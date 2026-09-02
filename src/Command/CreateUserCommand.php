<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create an account (there is no self-registration).',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly UserRepository $users,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail used to log in')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password (asked interactively when omitted)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant the administrator role')
            ->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Do nothing when the e-mail is already used')
            ->addOption('only-if-empty', null, InputOption::VALUE_NONE, 'Do nothing when at least one account already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if ($input->getOption('only-if-empty') && $this->users->countAll() > 0) {
            $io->note('Accounts already exist, nothing to do.');

            return Command::SUCCESS;
        }

        if (\count($this->validator->validate($email, [new Email()])) > 0 || '' === $email) {
            $io->error(\sprintf('"%s" is not a valid e-mail address.', $email));

            return Command::INVALID;
        }

        if (null !== $this->users->findOneBy(['email' => $email])) {
            if ($input->getOption('if-not-exists')) {
                $io->note(\sprintf('Account "%s" already exists, nothing to do.', $email));

                return Command::SUCCESS;
            }
            $io->error(\sprintf('An account with e-mail "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $password = $input->getArgument('password');
        if (null === $password || '' === $password) {
            $password = $io->askHidden('Password', static function (?string $value): string {
                if (null === $value || \strlen($value) < 8) {
                    throw new \RuntimeException('The password must be at least 8 characters long.');
                }

                return $value;
            });
        }

        $user = $this->userManager->create($email, (string) $password, (bool) $input->getOption('admin'));

        $io->success(\sprintf('Account "%s" created%s.', $user->getEmail(), $user->isAdmin() ? ' with the administrator role' : ''));

        return Command::SUCCESS;
    }
}
