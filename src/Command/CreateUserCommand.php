<?php

namespace App\Command;


namespace App\Command;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\GitSf;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CreateUserCommand extends Command
{

    // to make your command lazily loaded, configure the $defaultName static property,
    // so it will be instantiated only when the command is actually called.
    protected static $defaultName = 'app:add-user';
    /**
     * @var SymfonyStyle
     */
    private $io;
    private $entityManager;
    private $passwordEncoder;
    private $users;
    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $encoder, UserRepository $users)
    {
        parent::__construct();
        $this->entityManager = $em;
        $this->passwordEncoder = $encoder;
        $this->users = $users;
    }
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Creates users and stores them in the database')
            ->setHelp($this->getCommandHelp())
            // commands can optionally define arguments and/or options (mandatory and optional)
            // see https://symfony.com/doc/current/components/console/console_arguments.html
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (null !== $input->getArgument('username') && null !== $input->getArgument('password') && null !== $input->getArgument('email') && null !== $input->getArgument('full-name')) {
            return;
        }
        $this->io->title('Add User Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console app:add-user username password',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);
        // Ask for the username if it's not defined
        $username = $input->getArgument('username');
        if (null !== $username) {
            $this->io->text(' > <info>Username</info>: '.$username);
        } else {
            $username = $this->io->ask('Username');
            $input->setArgument('username', $username);
        }
        // Ask for the password if it's not defined
        $password = $input->getArgument('password');
        if (null !== $password) {
            $this->io->text(' > <info>Password</info>: '.str_repeat('*', mb_strlen($password)));
        } else {
            $password = $this->io->askHidden('Password (your type will be hidden)');
            $input->setArgument('password', $password);
        }
    }
    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('add-user-command');
        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        // make sure to validate the user data is correct
        $this->validateUserData($username);
        // create the user and encode its password
        $user = new User();
        $user->setUsername($username);
        // See https://symfony.com/doc/current/book/security.html#security-encoding-password
        $encodedPassword = $this->passwordEncoder->encodePassword($user, $plainPassword);
        $user->setPassword($encodedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $process = new Process(['mkdir', $username]);
        $process->setWorkingDirectory(GitSf::GIT_FOLDERS);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->io->success(sprintf('New user : %s', $user->getUsername()));
        $event = $stopwatch->stop('add-user-command');
        if ($output->isVerbose()) {
            $this->io->comment(sprintf('New user database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB', $user->getId(), $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }
    }
    private function validateUserData($username): void
    {
        // first check if a user with the same username already exists.
        $existingUser = $this->users->findOneBy(['username' => $username]);
        if (null !== $existingUser) {
            throw new RuntimeException(sprintf('There is already a user registered with the "%s" username.', $username));
        }
    }
    /**
     * The command help is usually included in the configure() method, but when
     * it's too long, it's better to define a separate method to maintain the
     * code readability.
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates new users and saves them in the database:
  <info>php %command.full_name%</info> <comment>username password email</comment>
By default the command creates regular users. To create administrator users,
add the <comment>--admin</comment> option:
  <info>php %command.full_name%</info> username password email <comment>--admin</comment>
If you omit any of the three required arguments, the command will ask you to
provide the missing values:
  # command will ask you for the email
  <info>php %command.full_name%</info> <comment>username password</comment>
  # command will ask you for the email and password
  <info>php %command.full_name%</info> <comment>username</comment>
  # command will ask you for all arguments
  <info>php %command.full_name%</info>
HELP;
    }
}