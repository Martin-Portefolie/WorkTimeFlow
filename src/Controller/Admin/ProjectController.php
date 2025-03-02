<?php

namespace App\Controller\Admin;


use App\Entity\Company;
use App\Entity\Project;
use App\Entity\Rate;
use App\Entity\Team;
use App\Form\ProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
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
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('search');
        $queryBuilder = $this->entityManager->getRepository(Project::class)->createQueryBuilder('p')
            ->leftJoin('p.client', 'c') // Join client table for searching
            ->leftJoin('p.teams', 't'); // Join teams for searching

        if ($searchTerm) {
            $queryBuilder->andWhere('p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        // Pagination setup
        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));


        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        $allTeams = $this->entityManager->getRepository(Team::class)->findAll();
        $company = $this->entityManager->getRepository(Company::class)->find(1);

        $projectsDataArray = [];

        foreach ($pagerfanta->getCurrentPageResults() as $project) {
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
                'last_updated'=>$project->getLastUpdated()?->format('Y-m-d'),
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
            'company' => $company,
            'pager' => $pagerfanta,
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

    /**
     * @throws \Exception
     */
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
        $project->setIsPaid($request->request->has('is_paid'));


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
        $selectedRateId = $request->request->get('rate'); // Get rate from form submission

        if ($selectedRateId) {
            $rate = $this->entityManager->getRepository(Rate::class)->find($selectedRateId);
            if ($rate) {
                $project->setRate($rate); // Assign rate object to project
            }
        } else {
            $project->setRate(null);
        }

        // Handle Deadline
        $deadlineValue = $request->request->get('deadline');
        if ($deadlineValue) {
            $project->setDeadline(new \DateTime($deadlineValue));
        } else {
            $project->setDeadline(null);
        }





        // Manage team associations
        $selectedTeamIds = $request->request->all('team_ids', []);

        // Detach unselected teams
        foreach ($project->getTeams() as $team) {
            if (!in_array($team->getId(), $selectedTeamIds)) {
                $project->removeTeam($team);
            }
        }

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
