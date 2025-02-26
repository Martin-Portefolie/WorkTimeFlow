<?php

namespace App\Controller\Admin;

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

        $projectsDataArray = [];
        foreach ($projects as $project) {
            $projectsDataArray[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'client' => $project->getClient()->getName(),
                'teams' => $project->getTeams(),
                'todo' => $project->getTodos(),
                'active' => $project->isActive(),
                'total_minutes' => $project->getTotalMinutesUsed()
            ];
        }

        return $this->render('admin/project/index.html.twig', [
            'projectDataArray' => $projectsDataArray,
            'allTeams' => $allTeams, // Pass all teams for selection
        ]);
    }

    #[Route('/admin/project/new', name: 'admin_project_new')]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persist selected teams directly from the form data
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

        // Directly update from the request data, as fields are in the same form on the `index.html.twig`
        $project->setName($request->request->get('name'));
        $project->setDescription($request->request->get('description'));
        $project->setActive($request->request->has('active'));

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