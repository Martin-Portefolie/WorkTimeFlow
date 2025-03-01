<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\Project;
use App\Entity\Team;
use App\Form\ProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/project', name: 'admin_project')]
    public function index(): Response
    {
        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        $allTeams = $this->entityManager->getRepository(Team::class)->findAll();
        $company = $this->entityManager->getRepository(Company::class)->find(1);

        $projectsDataArray = [];
        foreach ($projects as $project) {
            $projectsDataArray[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'client' => $project->getClient()->getName(),
                'teams' => $project->getTeams()->toArray(), // Convert collection to array
                'todo' => $project->getTodos()->toArray(), // Convert collection to array
                'is_archived' => $project->isArchived(),
                'total_minutes' => $project->getTotalMinutesUsed(),
                'deadline' => $project->getDeadline()?->format('Y-m-d'),
                'priority' => $project->getPriority()->value,
                'estimated_budget' => $project->getEstimatedBudget(),
                'estimated_minutes' => $project->getEstimatedMinutes() ?? 0,
                'remaining_minutes' => $project->getRemainingMinutes() ?? 0,
                'rate' => $project->getRate(),
                'is_paid' => $project->isPaid(),
            ];
        }

        return $this->render('admin/project/index.html.twig', [
            'projectDataArray' => $projectsDataArray,
            'allTeams' => $allTeams,
            'company' => $company
        ]);
    }

    #[Route('/admin/project/new', name: 'admin_project_new')]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedTeams = $form->get('teams')->getData();
            foreach ($selectedTeams as $team) {
                $project->addTeam($team);
            }

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_project');
        }

        return $this->render('admin/project/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/project/update/{id}', name: 'admin_project_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        // name, description, is_archived
        $project->setName($request->request->get('name'));
        $project->setDescription($request->request->get('description'));
        $project->setArchived($request->request->has('is_archived'));

        // Priority
        $priorityValue = $request->request->get('priority');
        if (in_array($priorityValue, \App\Enum\Priority::getValues())) {
            $project->setPriority(\App\Enum\Priority::from($priorityValue));
        }

        // Budget
        $budgetValue = $request->request->get('estimated_budget');
        $project->setEstimatedBudget(null !== $budgetValue ? (float) $budgetValue : null);

        // Estimated hours

        $estimatedTime = $request->request->get('estimated_time');
        $project->setEstimatedTime(null !== $estimatedTime ? (int) $estimatedTime : 0);

        // Handle Rate Selection
        $selectedRate = $request->request->get('rate');
        $project->setRate($selectedRate);

        // Paid Status
        $isPaid = $request->request->has('is_paid');
        $project->setIsPaid($isPaid);


        // Manage team associations
        $selectedTeamIds = $request->request->all('team_ids', []);

        // Detach unselected teams
        foreach ($project->getTeams() as $team) {
            if (!in_array($team->getId(), $selectedTeamIds)) {
                $project->removeTeam($team);
            }
        }

        // Attach selected teams
        foreach ($selectedTeamIds as $teamId) {
            $team = $this->entityManager->getRepository(Team::class)->find($teamId);
            if ($team && !$project->getTeams()->contains($team)) {
                $project->addTeam($team);
            }
        }



        $this->entityManager->flush();

        return $this->redirectToRoute('admin_project');
    }
}
