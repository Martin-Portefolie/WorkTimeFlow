<?php

namespace App\Controller\profile;

use App\Entity\Timelog;
use App\Entity\Todo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TimeRegisterController extends AbstractController
{ private $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws \Exception
     */
    #[Route('/profile/time-register/{week?}-{year?}', name: 'app_time_register', methods: ['GET'])]
    public function index(Request $request, int $week = null, int $year = null): Response
    {
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $currentDate = new \DateTime('now', $timezone);

        if (!$week || !$year) {
            $week = (int)$currentDate->format('W');
            $year = (int)$currentDate->format('Y');
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

        $data = [];
        for ($i = 0; $i < 7; $i++) {
            $day = clone $startOfWeek;
            $day->modify("+$i day");

            $data[] = [
                'date' => $day,
                'todo' => [],
                'timelog' => [],
            ];
        }

        // Fetch the current user
        /** @var User|null $user */
        $user = $this->getUser();

        // Get the user's teams
        $userTeams = $user->getTeams();

        // Fetch Todos associated with Projects linked to the user's teams
        $todos = $this->entityManager->getRepository(Todo::class)->createQueryBuilder('t')
            ->join('t.project', 'p')
            ->join('p.teams', 'team')
            ->where('team IN (:teams)')
            ->setParameter('teams', $userTeams)
            ->getQuery()
            ->getResult();

        foreach ($todos as $todo) {
            foreach ($data as &$day) {
                $dayStart = (clone $day['date'])->setTime(0, 0);
                $dayEnd = (clone $day['date'])->setTime(23, 59, 59);

                if ($todo->getDateStart() <= $dayEnd && $todo->getDateEnd() >= $dayStart) {
                    $day['todo'][] = $todo;
                }

                foreach ($todo->getTimelogs() as $timelog) {
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

        $weeklyTotal = 0;
        foreach ($data as &$day) {
            $dayTotal = 0;
            foreach ($day['timelog'] as $timelog) {
                $dayTotal += $timelog['hours'] * 60 + $timelog['minutes'];
            }
            $day['dayTotal'] = $dayTotal;
            $weeklyTotal += $dayTotal;
        }

        return $this->render('profile/time_register/index.html.twig', [
            'week' => $week,
            'year' => $year,
            'weeklyData' => $data,
            'weeklyTotal' => $weeklyTotal,
            'todos' => $todos,

        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/profile/todo-handler/save-time', name: 'app_todo_handler_save_time', methods: ['POST'])]
    public function saveTime(Request $request): JsonResponse
    {
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
            $timelog->setDate(new \DateTime($date));
            $this->entityManager->persist($timelog);
        }

        $timelog->setHoursAndMinutes((int) $hours, (int) $minutes);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'todoId' => $todoId,
            'date' => $date,
            'hours' => $timelog->getHours(),
            'minutes' => $timelog->getMinutes(),
        ]);
    }
}