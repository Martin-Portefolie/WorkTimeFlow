<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Project;
use App\Enum\Priority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-project',
    description: 'Creates a new project for a predefined client',
)]
class CreateProjectCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find the client "Heste-status aps"
        $client = $this->entityManager->getRepository(Client::class)
            ->findOneBy(['name' => 'Heste-status aps']);

        if (!$client) {
            $io->error('Client "Heste-status aps" not found. Please create the client first.');
            return Command::FAILURE;
        }

        // Create Project and link it to the client
        $project = new Project();
        $project->setName('Project Pegasus');
        $project->setDescription('A new project owned by Heste-status aps');
        $project->setClient($client);
        $project->setArchived(false);
        $project->setPriority(Priority::MEDIUM);
        $project->setDeadline(new \DateTime('+30 days')); // Default deadline = 30 days from now
        $project->setEstimatedBudget(50000.00);
        $project->setEstimatedTime(1, 40); // 1h 40m instead of hardcoded minutes

        // Persist and save to database
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $io->success('Project "Project Pegasus" created for client "Heste-status aps".');

        return Command::SUCCESS;
    }
}

