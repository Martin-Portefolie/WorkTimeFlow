<?php

namespace App\Controller\profile;

use App\Entity\Todo;
use App\Entity\User;
use App\Form\TimelogType;
use App\Repository\TimelogRepository;
use App\Repository\TodoRepository;
use App\Service\UserProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TestController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserProjectService $userProjectService;
    private TodoRepository $todoRepository;
    private TimelogRepository $timelogRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserProjectService $userProjectService,
        TodoRepository $todoRepository,
        TimelogRepository $timelogRepository
    ) {
        $this->entityManager = $entityManager;
        $this->userProjectService = $userProjectService;
        $this->todoRepository = $todoRepository;
        $this->timelogRepository = $timelogRepository;
    }

    /**
     * ✅ Weekly Time Registration Overview
     */
    #[Route('/profile/time-register/{week?}-{year?}', name: 'app_time_register', methods: ['GET'])]
    public function timeRegister(?int $week = null, ?int $year = null): Response
    {
        # 1.0 User Check
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        # 2.0 Handle Week Navigation
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $currentDate = new \DateTime('now', $timezone);

        if (!$week || !$year) {
            $week = (int) $currentDate->format('W');
            $year = (int) $currentDate->format('Y');
        }

        if ($week < 1) {
            $week = 53;
            $year--;
        } elseif ($week > 53) {
            $week = 1;
            $year++;
        }

        $startOfWeek = new \DateTime();
        $startOfWeek->setISODate($year, $week)->setTime(0, 0, 0);

        # 3.0 Generate Weekly Calendar Data
        $weeklyData = [];
        for ($i = 0; $i < 7; $i++) {
            $day = clone $startOfWeek;
            $day->modify("+$i day");

            $weeklyData[] = [
                'date' => $day,
                'todos' => [],
                'timelog' => [],
            ];
        }

        # 4.0 Fetch Todos Using Repository
        $projects = $this->userProjectService->getProjectsByUser($user);
        $todos = $this->todoRepository->findTodosByUserProjects($user, $projects);

        # 5.0 Fetch Timelogs for the Week
        $timelogs = $this->timelogRepository->findTimelogsByUserAndWeek($user, $week, $year);

        # 6.0 Map Todos and Timelogs to the Weekly Calendar
        foreach ($todos as $todoData) {
            $todo = $todoData[0];

            foreach ($weeklyData as &$day) {
                $dayStart = (clone $day['date'])->setTime(0, 0);
                $dayEnd = (clone $day['date'])->setTime(23, 59, 59);

                if ($todo->getDateStart() <= $dayEnd && $todo->getDateEnd() >= $dayStart) {
                    $day['todos'][] = [
                        'id' => $todo->getId(),
                        'name' => $todo->getName(),
                        'status' => $todo->getStatus()->value,
                        'project_name' => $todo->getProject()->getName(),
                    ];
                }

                # Assign timelogs to the correct day
                foreach ($timelogs as $timelog) {
                    if ($timelog->getDate() >= $dayStart && $timelog->getDate() <= $dayEnd) {
                        $day['timelog'][] = [
                            'id' => $timelog->getId(),
                            'todo_id' => $todo->getId(),
                            'description' => $timelog->getDescription(),
                            'hours' => $timelog->getHours(),
                            'minutes' => $timelog->getMinutes(),
                            'date' => $timelog->getDate()->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
        }

        # 7.0 Calculate Weekly Total
        $weeklyTotal = array_reduce($weeklyData, fn ($carry, $day) => $carry + array_sum(array_column($day['timelog'], 'hours')) * 60 + array_sum(array_column($day['timelog'], 'minutes')), 0);

        return $this->render('profile/time_register/index.html.twig', [
            'week' => $week,
            'year' => $year,
            'weeklyData' => $weeklyData,
            'weeklyTotal' => $weeklyTotal,
            'todos' => $todos,
        ]);
    }

    #[Route('/profile/time-register/add', name: 'add_time_log')]
    public function addTimeLog(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        $timelog = new Timelog();
        $form = $this->createForm(TimelogType::class, $timelog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($timelog);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_time_register');
        }

        return $this->render('profile/time_register/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
