<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\User;
use App\Form\TeamType;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamsController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/team', name: 'admin_team')]
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('search');

        // Query Builder for searching & pagination
        $queryBuilder = $this->entityManager->getRepository(Team::class)->createQueryBuilder('t');

        if ($searchTerm) {
            $queryBuilder->andWhere('t.name LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%');
        }

        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));
        $allUsers = $this->entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/teams/index.html.twig', [
            'teams' => $pagerfanta,
            'allUsers' => $allUsers,
        ]);
    }

    #[Route('/admin/team/new', name: 'admin_team_new')]
    public function new(Request $request): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persist selected users directly from the form data
            $selectedUsers = $form->get('users')->getData();
            foreach ($selectedUsers as $user) {
                $team->addUser($user);
            }

            $this->entityManager->persist($team);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_team');
        }

        return $this->render('admin/teams/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/team/update/{id}', name: 'admin_team_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // Update the team name
        $team->setName($request->request->get('name'));

        // Retrieve selected user IDs as an array
        $selectedUserIds = $request->request->all('user_ids') ?? [];

        // Remove users that are no longer selected
        foreach ($team->getUsers() as $user) {
            if (!in_array($user->getId(), $selectedUserIds)) {
                $team->removeUser($user);
            }
        }

        // Add selected users
        foreach ($selectedUserIds as $userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user && !$team->getUsers()->contains($user)) {
                $team->addUser($user);
            }
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('admin_team');
    }
}
