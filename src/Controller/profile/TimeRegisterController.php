<?php

namespace App\Controller\profile;

use App\Entity\Timelog;
use App\Entity\Todo;
use App\Entity\User;
use App\Service\DateService;
use App\Service\UserProjectService;
use App\Repository\TimelogRepository;
use App\Repository\TodoRepository;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        DateService $dateService,
        UserProjectService $userProjectService,
        private readonly TimelogRepository $timelogRepository,
        private readonly TodoRepository $todoRepository,
    )
    {
        $this->entityManager = $entityManager;
        $this->dateService = $dateService;
        $this->userProjectService = $userProjectService;
    }

    /**
     * @throws \Exception
     */
    #[Route('/profile/time-register/{week<\d+>?}/{year<\d+>?}', name: 'app_time_register', methods: ['GET'])]
    public function index(Request $request, ?int $week = null, ?int $year = null): Response
    {
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $yearWeekData = $this->dateService->getWeekYear($timezone, $week, $year);
        $week = $yearWeekData['week'];
        $year = $yearWeekData['year'];
        $weekData = $this->dateService->getWeek($week, $year);
        $data = $weekData['data'];

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        $todos = $this->todoRepository->findAccessibleToUser($user);


        $timelogs = $this->timelogRepository->findTimelogsByUserAndWeek($user, $week, $year);

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
            'weeklyTotal' => $weeklyTotal,
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
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON.'], 400);
        }
        $todoId = $data['todoId'] ?? null;
        $date = $data['date'] ?? null;
        $hours = $data['hours'] ?? 0;
        $minutes = $data['minutes'] ?? 0;

        if (!$todoId || !$date) {
            return new JsonResponse(['error' => 'Missing parameters.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $day = is_string($date) ? \DateTime::createFromFormat('!Y-m-d', $date) : false;
        if (!$day || $day->format('Y-m-d') !== $date
            || !is_int($hours) || $hours < 0 || $hours > 24
            || !is_int($minutes) || $minutes < 0 || $minutes > 59
            || ($hours * 60 + $minutes) > 1440
            || filter_var($todoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return $this->json(['error' => 'Use a valid date and a duration between 0 and 24 hours.'], 400);
        }

        $todo = $this->todoRepository->find($todoId);
        if (!$todo) {
            return new JsonResponse(['error' => 'Todo not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$this->userProjectService->canUserAccessTodo($user, $todo)) {
            return $this->json(['error' => 'You do not have access to this todo.'], 403);
        }

        // Match the owner as well as the Todo and calendar day.
        $timelogs = $this->timelogRepository->findForUserTodoOnDate($user, $todo, $day);

        if (count($timelogs) > 1) {
            return $this->json(['error' => 'Multiple entries exist for this day. Edit individual timelogs instead.'], 409);
        }
        $timelog = $timelogs[0] ?? null;

        if (!$timelog) {
            $timelog = new Timelog();
            $timelog->setTodo($todo);
            $timelog->setUser($user);
            $timelog->setDate($day);
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
