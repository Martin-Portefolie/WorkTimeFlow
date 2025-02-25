<?php

namespace App\Controller\profile;

use App\Entity\Project;
use App\Entity\Todo;
use App\Entity\User;
use App\Enum\TodoStatus;
use App\Form\Profile\TodoType;
use App\Service\UserProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class StatusController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserProjectService $userProjectService;

    public function __construct(EntityManagerInterface $entityManager, UserProjectService $userProjectService)
    {
        $this->entityManager = $entityManager;
        $this->userProjectService = $userProjectService;
    }

    #[Route('/profile/status/{year?}/{month?}', name: 'app_status', defaults: ['year' => null, 'month' => null])]
    public function index(?int $year = null, ?int $month = null): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'User not logged in'], 403);
        }


        # 2.0 Get teams and projects
        $teams = $this->userProjectService->getUserTeams($user);
        $projects = $this->userProjectService->getProjectsByUser($user);
        $teamProjects = $this->userProjectService->mapTeamsToProjects($teams, $projects);


        # 3.0 Get Todos
        $todos = $this->userProjectService->getTodosByUserProjects($user, $projects);
        $todoData = [];
        foreach ($todos as $todo) {
            if (!is_array($todo) || !isset($todo[0])) {
                continue; // Skip invalid todos
            }

            $todoData[] = [
                'id' => $todo[0]->getId(),
                'name' => $todo[0]->getName(),
                'status' => $todo[0]->getStatus()->value,
                'project_id' => $todo[0]->getProject()->getId(),
                'project_name' => $todo[0]->getProject()->getName(),
                'totalMinutesLogged' => $todo['totalMinutesLogged'] ?? 0, // Ensure this is always available
            ];
        }

        # 3.0 Prepare calendar data
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }
        if (!$month) {
            $month = (int) (new \DateTime())->format('m');
        }
        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }

        $calendarData = $this->generateCalendarData($year, $month);
        $this->assignTodosToCalendar($calendarData, $todos);

        # 4.0 Render view
        return $this->render('/profile/status/index.html.twig', [
            'controller_name' => 'StatusController',
            'user' => $user->getUsername(),
            'teams' => $teams,
            'teamProjects' => $teamProjects,
            'projects' => $projects,
            'todos' => $todoData,
            'calendar' => $calendarData,
            'year' => $year,
            'month' => $month,
        ]);
    }

    #[Route('/todo/update-status/{id}', name: 'update_todo_status', methods: ['POST'])]
    public function updateTodoStatus(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        $todo = $this->entityManager->getRepository(Todo::class)->find($id);
        if (!$todo || !$todo->getProject()->getTeams()->exists(fn($key, $team) => $user->getTeams()->contains($team))) {
            return $this->json(['error' => 'Unauthorized access'], 403);
        }

        $status = $request->request->get('status');
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'paused', 'review'])) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        // Convert the string status from the request to an Enum
        $statusString = $request->request->get('status');
        $statusEnum = TodoStatus::tryFrom($statusString);

        if (!$statusEnum) {
            return $this->json(['error' => 'Invalid status'], 400);
        }


        $todo->setStatus($statusEnum);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_status');
    }

    #[Route('/todo/new', name: 'new_todo')]
    public function newTodo(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($todo);
            $this->entityManager->flush();

            return new RedirectResponse($this->generateUrl('app_status'));
        }

        return $this->render('/profile/status/new_todo.html.twig', [
            'form' => $form->createView(),
        ]);
    }




    /**
     *  5.0 Generate calendar data for the given month.
     * @throws \Exception
     */
    private function generateCalendarData(?int $year, ?int $month): array
    {
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $currentDate = new \DateTime('now', $timezone);

        $year = $year ?? (int) $currentDate->format('Y');
        $month = $month ?? (int) $currentDate->format('m');

        $startOfMonth = new \DateTimeImmutable("$year-$month-01", $timezone);
        $daysInMonth = (int) $startOfMonth->format('t');

        $calendarData = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $currentDay = $startOfMonth->modify("+$i days");

            $calendarData[] = [
                'date' => $currentDay,
                'todos' => [],
                'timelog' => [],
            ];
        }

        return $calendarData;
    }

    /**
     *  6.0 Assign todos and timelogs to the correct days in the calendar.
     */
    private function assignTodosToCalendar(array &$calendarData, array $todos): void
    {
        $timezone = new \DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');

        foreach ($todos as $todoData) {
            $todo = $todoData[0];
            $totalMinutes = $todoData['totalMinutesLogged'] ?? 0;

            $todoStart = $todo->getDateStart();
            $todoEnd = $todo->getDateEnd();

            if ($todoEnd < $todoStart) {
                continue;
            }

            foreach ($calendarData as &$day) {
                $dayStart = new \DateTime($day['date']->format('Y-m-d'), $timezone);
                $dayEnd = (clone $dayStart)->setTime(23, 59, 59);

                if ($todoStart <= $dayEnd && $todoEnd >= $dayStart) {
                    $hours = intdiv($totalMinutes, 60);
                    $minutes = $totalMinutes % 60;

                    if ($totalMinutes  <= 0) {
                        $status = "Pending";
                    } elseif ($totalMinutes > 0) {
                        $status = "In Progress";
                    }

                    $day['todos'][] = [
                        'id' => $todo->getId(),
                        'name' => $todo->getName(),
                        'project_name' => $todo->getProject() ? $todo->getProject()->getName() : 'Unknown Project',
                        'logged_time' => sprintf("%dh %02dm", $hours, $minutes),
                        'status' => $status,
                    ];
                }
            }
        }
    }
}
