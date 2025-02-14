<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-project',
    description: 'Add a short description for your command',
)]
class CreateProjectCommand extends Command
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
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find the client "Heste-test aps"
        $client = $this->entityManager->getRepository(Client::class)
            ->findOneBy(['name' => 'Heste-test aps']);

        if (!$client) {
            $io->error('ClientFixtures "Heste-test aps" not found. Please create the client first.');
            return Command::FAILURE;
        }

        // Create Project and link it to the client
        $project = new Project();
        $project->setName('Project Pegasus');
        $project->setDescription('A new project owned by Heste-test aps');
        $project->setClient($client);
        $project->setActive(true);

        // Persist and save to database
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $io->success('Project "Project Pegasus" created for client "Heste-test aps".');

        return Command::SUCCESS;
    }
}
