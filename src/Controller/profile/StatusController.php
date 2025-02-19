<?php

namespace App\Controller\profile;

use App\Entity\Project;
use App\Entity\Todo;
use App\Entity\User;
use App\Enum\TodoStatus;
use App\Form\Profile\TodoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


final class StatusController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/profile/status/{year?}/{month?}', name: 'app_status', defaults: ['year' => null, 'month' => null])]
    public function index(?int $year = null, ?int $month = null): Response
    {
        # 1.0 User check
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return $this->json(['error' => 'User not logged in'], 403);
        }

        # 2.0 Get teams and projects
        $teams = $this->getUserTeams($user);
        $projects = $this->getProjectsByUser($user);
        $teamProjects = $this->mapTeamsToProjects($teams, $projects);

        # 3.0 Get Todos
        $todos = $this->getTodosByUserProjects($user, $projects);
        $todoData = [];
        foreach ($todos as $todo) {
            $todoData[] = [
                'id' => $todo[0]->getId(),
                'name' => $todo[0]->getName(),
                'status' => $todo[0]->getStatus()->value, // Ensure we get the status as a string
                'project_id' => $todo[0]->getProject()->getId(),
                'project_name' => $todo[0]->getProject()->getName(),
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

            return new RedirectResponse($this->generateUrl('app_test'));
        }

        return $this->render('status/profile/new_todo.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     *  1.0 Get authenticated user.
     */
    private function getAuthenticatedUser(): ?User
    {
        return $this->getUser();
    }

    /**
     *  2.0 Get teams the user belongs to.
     */
    private function getUserTeams(User $user): array
    {
        return $user->getTeams()->toArray();
    }

     /**
     * 2.1 Maps teams to their assigned projects.
     *
     * This function takes an array of teams and an array of projects,
     * then organizes the projects under each team they belong to.
     */
    private function mapTeamsToProjects(array $teams, array $projects): array
    {
        $teamProjects = [];

        foreach ($teams as $team) {
            $teamProjects[$team->getName()] = [];
            foreach ($projects as $project) {
                if ($project->getTeams()->contains($team)) {
                    $teamProjects[$team->getName()][] = [
                        'id' => $project->getId(),
                        'name' => $project->getName(),
                    ];
                }
            }
        }

        return $teamProjects;
    }

    /**
     *  3.0 Fetch projects for user.
     */
    private function getProjectsByUser(User $user): array
    {
        return $this->entityManager->getRepository(Project::class)->findProjectsByUser($user);
    }

    /**
     *  4.0 Fetch todos linked to user’s projects.
     */
    private function getTodosByUserProjects(User $user, array $projects): array
    {
        return $this->entityManager->getRepository(Todo::class)->findTodosByUserProjects($user, $projects);
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
