<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'create-user',
    description: 'Add a short description for your command',
)]
class CreateUserCommand extends Command
{


    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create Admin User
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('a@a.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($admin);

        // Create Regular User 1
        $user1 = new User();
        $user1->setUsername('Hest');
        $user1->setEmail('b@b.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'user123'));
        $user1->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user1);

        // Create Regular User 2
        $user2 = new User();
        $user2->setUsername('Test');
        $user2->setEmail('c@c.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'user123'));
        $user2->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user2);

        // Save all three users
        $this->entityManager->flush();

        $io->success('Test users created: Admin (a@a.com / admin123), User1 (b@b.com / Hest), and User2 (c@c.com / Test)');

        return Command::SUCCESS;
    }
}
