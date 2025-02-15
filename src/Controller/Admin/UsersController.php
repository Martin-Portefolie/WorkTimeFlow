<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Team;
use App\Entity\User;
use App\Form\ClientType;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;


class UsersController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/admin/user', name: 'admin_users')]
    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $allTeams = $this->entityManager->getRepository(Team::class)->findAll();

        $userDataArray = [];
        foreach ($users as $user) {
            $userDataArray[] = [
                'id' => $user->getId(),
                'name' => $user->getUsername(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'teams' => $user->getTeams(),
                'roles' => $user->getRoles(),
            ];
        }

        return $this->render('admin/users/index.html.twig', [
            'userDataArray' => $userDataArray,
            'allTeams' => $allTeams,
        ]);
    }

    #[Route('/admin/user/new', name: 'admin_user_new')]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set default role if no roles are assigned
            if (empty($user->getRoles())) {
                $user->setRoles(['ROLE_USER']);
            }

            // Hash password
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/user/update/{id}', name: 'admin_user_update', methods: ['POST'])]
    public function update(Request $request, int $id, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Update basic fields
        $user->setUsername($request->request->get('name'));
        $user->setEmail($request->request->get('email'));

        // Update roles
        $roles = $request->request->all('roles'); // Use all() for array data
        $user->setRoles(!empty($roles) ? $roles : $user->getRoles());

        // Update password if provided
        $newPassword = $request->request->get('new_password');
        if (!empty($newPassword)) {
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
        }

        // Update team associations
        $selectedTeamIds = $request->request->all('team_ids'); // Use all() for array data
        foreach ($user->getTeams() as $team) {
            if (!in_array($team->getId(), $selectedTeamIds)) {
                $user->removeTeam($team);
            }
        }
        foreach ($selectedTeamIds as $teamId) {
            $team = $this->entityManager->getRepository(Team::class)->find($teamId);
            if ($team && !$user->getTeams()->contains($team)) {
                $user->addTeam($team);
            }
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('admin_users');
    }



}