<?php

namespace App\Controller\profile;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $startDate = new \DateTime('monday this week'); // Start of the current week
        $endDate = clone $startDate;
        $endDate->modify('+6 days'); // End of the week

        $projects = [
            ['id' => 1, 'name' => 'Project Alpha'],
            ['id' => 2, 'name' => 'Project Beta'],
            ['id' => 3, 'name' => 'Project Gamma'],
        ];
        // Dummy todos
        $todos1 = [
            ['id' => 1, 'name' => 'Fix login bug', 'status' => 'Åben', 'date' => '2025-02-10'],
            ['id' => 2, 'name' => 'Update documentation', 'status' => 'I gang', 'date' => '2025-02-12'],
            ['id' => 3, 'name' => 'Refactor database queries', 'status' => 'Afsluttet', 'date' => '2025-02-15'],
        ];

        // Dummy timelog data
        $timelogs = [
            ['id' => 1, 'todo_id' => 1, 'user_id' => 1, 'hours' => 4, 'date' => '2025-02-10'],
            ['id' => 2, 'todo_id' => 2, 'user_id' => 1, 'hours' => 3, 'date' => '2025-02-12'],
            ['id' => 3, 'todo_id' => 3, 'user_id' => 1, 'hours' => 5, 'date' => '2025-02-15'],
        ];

        $todos = [
            [
                'name' => 'Martin Eksamens forberedelse',
                'project' => 'Team Management', // 🛠 Add project name here
                'hours' => [
                    '2025-02-03' => 7.5,
                    '2025-02-04' => 7.5,
                    '2025-02-05' => 7.5,
                    '2025-02-06' => 7.5,
                    '2025-02-07' => 7,
                    '2025-02-08' => 0,
                    '2025-02-09' => 0,
                ]
            ],
            [
                'name' => 'Ny Udviklingsopgave',
                'project' => 'Software Development', // 🛠 Another project
                'hours' => [
                    '2025-02-03' => 6,
                    '2025-02-04' => 6,
                    '2025-02-05' => 8,
                    '2025-02-06' => 4,
                    '2025-02-07' => 5,
                    '2025-02-08' => 0,
                    '2025-02-09' => 0,
                ]
            ]
        ];


        return $this->render('profile/index.html.twig', [
            'controller_name' => 'ProfileController',
            'projects' => $projects,
            'todos' => $todos,
            'todos1' => $todos1,
            'timelogs' => $timelogs,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }
}
