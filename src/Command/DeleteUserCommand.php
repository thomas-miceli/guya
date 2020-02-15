<?php

namespace App\Command;


namespace App\Command;

use App\Repository\UserRepository;
use App\Util\GitHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;

class DeleteUserCommand extends Command
{

    // to make your command lazily loaded, configure the $defaultName static property,
    // so it will be instantiated only when the command is actually called.
    protected static $defaultName = 'guya:ru';
    /**
     * @var SymfonyStyle
     */
    private $io;
    private $entityManager;
    private $users;

    public function __construct(EntityManagerInterface $em, UserRepository $users)
    {
        parent::__construct();
        $this->entityManager = $em;
        $this->users = $users;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Deletes an existing user')
            ->setHelp($this->getCommandHelp())
            // commands can optionally define arguments and/or options (mandatory and optional)
            // see https://symfony.com/doc/current/components/console/console_arguments.html
            ->addArgument('username', InputArgument::REQUIRED, 'Username');
    }

    /**
     * The command help is usually included in the configure() method, but when
     * it's too long, it's better to define a separate method to maintain the
     * code readability.
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command deletes existing users:
  <info>php %command.full_name%</info> <comment>username</comment>
  <info>php %command.full_name%</info>
HELP;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (null !== $input->getArgument('username')) {
            return;
        }
        $this->io->title('Remove User Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console guya:ru username',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);
        // Ask for the username if it's not defined
        $username = $input->getArgument('username');
        if (null !== $username) {
            $this->io->text(' > <info>Username</info>: ' . $username);
        } else {
            $username = $this->io->ask('Username');
            $input->setArgument('username', $username);
        }
    }

    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('rm-user-command');
        $username = $input->getArgument('username');
        // make sure to validate the user data is correct
        $this->validateUserData($username);
        // create the user and encode its password
        $user = $this->users->findOneBy(['username' => $username]);
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $process = new Process(['rm', '-rf', $username]);
        $process->setWorkingDirectory(GitHelper::GIT_FOLDERS_CMD);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->io->success(sprintf('Deleted user : %s', $user->getUsername()));
        $event = $stopwatch->stop('rm-user-command');
        if ($output->isVerbose()) {
            $this->io->comment(sprintf('New user database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB', $user->getId(), $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }
    }

    private function validateUserData($username): void
    {
        $existingUser = $this->users->findOneBy(['username' => $username]);
        if (null === $existingUser) {
            throw new RuntimeException(sprintf('There is no user registered with the "%s" username.', $username));
        }
    }
}