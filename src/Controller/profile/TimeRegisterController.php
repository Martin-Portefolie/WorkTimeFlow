<?php

namespace App\Controller\profile;

use App\Entity\Timelog;
use App\Entity\Todo;
use App\Entity\User;
use App\Service\DateService;
use App\Service\UserProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TimeRegisterController extends AbstractController
{
    private $entityManager;
    private $dateService;
    private UserProjectService $userProjectService;

    public function __construct(EntityManagerInterface $entityManager, DateService $dateService, UserProjectService $userProjectService)
    {
        $this->entityManager = $entityManager;
        $this->dateService = $dateService;
        $this->userProjectService = $userProjectService;
    }

    /**
     * @throws \Exception
     */

    #[Route('/profile/time-register/{week<\d+>?}/{year<\d+>?}', name: 'app_time_register', methods: ['GET'])]
    public function index(Request $request, int $week = null, int $year = null): Response
    {

        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $yearWeekData = $this->dateService->getWeekYear($timezone, $week, $year);
        $week = $yearWeekData['week'];
        $year = $yearWeekData['year'];
        $weekData = $this->dateService->getWeek($week, $year);
        $data = $weekData['data'];


        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        $userTeams = $user->getTeams();

        #TODO Should go into repository
        $todos = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t')
            ->join('t.project', 'p')
            ->join('p.teams', 'team')
            ->where('team IN (:teams)')
            ->setParameter('teams', $userTeams)
            ->getQuery()
            ->getResult();



//        $weeklyTotal = 0;
//        foreach ($data as &$day) {
//            $dayTotal = 0;
//            foreach ($day['timelog'] as $timelog) {
//                $dayTotal += $timelog['hours'] * 60 + $timelog['minutes'];
//            }
//            $day['dayTotal'] = $dayTotal;
//            $weeklyTotal += $dayTotal;
//        }




        // ✅ Fetch timelogs for the current user & week
        $timelogs = $this->entityManager->getRepository(Timelog::class)
            ->findTimelogsByUserAndWeek($user, $week, $year);



        // ✅ Assign timelogs to the correct day in weeklyData
        foreach ($timelogs as $timelog) {
            foreach ($data as &$day) {
                if ($timelog->getDate()->format('Y-m-d') === $day['date']->format('Y-m-d')) {
                    $day['timelog'][] = [
                        'id' => $timelog->getId(),
                        'todo_id' => $timelog->getTodo()->getId(),
                        'description' => $timelog->getDescription(),
                        'hours' => $timelog->getHours(),
                        'minutes' => $timelog->getMinutes(),
                        'date' => $timelog->getDate()->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        // ✅ Calculate weekly total
        $weeklyTotal = 0;
        foreach ($data as &$day) {
            $dayTotal = 0;
            foreach ($day['timelog'] as $timelog) {
                $dayTotal += $timelog['hours'] * 60 + $timelog['minutes'];
            }
            $day['dayTotal'] = $dayTotal;
            $weeklyTotal += $dayTotal;
        }

        return $this->render('/profile/time_register/index.html.twig', [
            'week' => $week,
            'year' => $year,
            'todos' => $todos,
            'weeklyData' => $data,
            'weeklyTotal' => $weeklyTotal
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/profile/save-time', name: 'app_save_time', methods: ['POST'])]
    public function saveTime(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $todoId = $data['todoId'] ?? null;
        $date = $data['date'] ?? null;
        $hours = $data['hours'] ?? 0;
        $minutes = $data['minutes'] ?? 0;

        if (!$todoId || !$date) {
            return new JsonResponse(['error' => 'Missing parameters.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $todo = $this->entityManager->getRepository(Todo::class)->find($todoId);
        if (!$todo) {
            return new JsonResponse(['error' => 'Todo not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Find or create a timelog for the given date
        $timelog = $todo->getTimelogs()->filter(function ($log) use ($date) {
            return $log->getDate()->format('Y-m-d') === $date;
        })->first();

        if (!$timelog) {
            $timelog = new Timelog();
            $timelog->setTodo($todo);
            $timelog->setUser($user);
            $timelog->setDate(new \DateTime($date));
            $this->entityManager->persist($timelog);
        }

        $timelog->setHoursAndMinutes((int) $hours, (int) $minutes);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'todoId' => $todoId,
            'userId' => $user->getId(),
            'date' => $date,
            'hours' => $timelog->getHours(),
            'minutes' => $timelog->getMinutes(),
        ]);
    }

}