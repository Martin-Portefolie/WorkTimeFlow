<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-team',
    description: 'Add a short description for your command',
)]
class CreateTeamCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('teamName', InputArgument::REQUIRED, 'The name of the team')
            ->addArgument('userEmails', InputArgument::IS_ARRAY, 'Emails of the users to add to the team')
            ->addOption('projectName', null, InputOption::VALUE_REQUIRED, 'Name of the project to associate with the team');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $teamName = $input->getArgument('teamName');
        $userEmails = $input->getArgument('userEmails');
        $projectName = $input->getOption('projectName');

        // Create the team
        $team = new Team();
        $team->setName($teamName);

        // Find and add users to the team
        foreach ($userEmails as $email) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $team->addUser($user);
                $io->success("Added user {$user->getUsername()} ({$email}) to team '{$teamName}'.");
            } else {
                $io->warning("User with email '{$email}' not found.");
            }
        }

        // Find the project and associate it with the team, if provided
        if ($projectName) {
            $project = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => $projectName]);
            if ($project) {
                $team->addProject($project);
                $io->success("Associated team '{$teamName}' with project '{$projectName}'.");
            } else {
                $io->error("Project '{$projectName}' not found.");

                return Command::FAILURE;
            }
        }

        // Persist and flush the team
        $this->entityManager->persist($team);
        $this->entityManager->flush();

        $io->success("Team '{$teamName}' has been created, users assigned, and associated with project '{$projectName}' if provided.");

        return Command::SUCCESS;
    }
}
