<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\Todo;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-todo',
    description: 'Add a short description for your command',
)]
class CreateTodoCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private ProjectRepository $projectRepository;

    public function __construct(EntityManagerInterface $entityManager, ProjectRepository $projectRepository)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectRepository = $projectRepository;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('projectId', InputArgument::REQUIRED, 'The ID of the project to associate the todo with')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the todo')
            ->addArgument('dateStart', InputArgument::REQUIRED, 'Start date of the todo (Y-m-d)')
            ->addArgument('dateEnd', InputArgument::REQUIRED, 'End date of the todo (Y-m-d)');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Retrieve input values
        $projectId = (int) $input->getArgument('projectId');
        $name = $input->getArgument('name');
        $dateStart = new \DateTime($input->getArgument('dateStart'));
        $dateEnd = new \DateTime($input->getArgument('dateEnd'));

        // Fetch the project entity
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            $io->error('Invalid project ID.');

            return Command::FAILURE;
        }

        // Create and save the todo entry
        $todo = new Todo();
        $todo->setName($name);
        $todo->setDateStart($dateStart);
        $todo->setDateEnd($dateEnd);
        $todo->setProject($project);

        $this->entityManager->persist($todo);
        $this->entityManager->flush();

        $io->success("Todo '{$name}' has been created and associated with project '{$project->getName()}'.");

        return Command::SUCCESS;
    }
}
