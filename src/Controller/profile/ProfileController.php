<?php

namespace App\Controller\profile;

use App\Entity\Todo;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    /**
     * @throws \Exception
     */
    #[Route('/profile/project-todo/{year?}/{month?}', name: 'app_profile', defaults: ['year' => null, 'month' => null])]
    public function index(?int $year = null, ?int $month = null): Response
    {
        $timezone = new DateTimeZone($_ENV['APP_TIMEZONE'] ?? 'Europe/Copenhagen');
        $currentDate = new DateTime('now', $timezone);

        // Default to current year and month if not provided
        if (!$year) {
            $year = (int)$currentDate->format('Y');
        }
        if (!$month || $month < 1 || $month > 12) {
            $month = (int)$currentDate->format('m');
        }

        // Get the number of days in the selected month
// Correct calculation of the start of the month
        $startOfMonth = new DateTimeImmutable("$year-$month-01", $timezone);
        $daysInMonth = (int) $startOfMonth->format('t'); // Correct number of days in the selected month

        $monthlyData = [];
        for ($i = 1; $i < $daysInMonth +1 ; $i++) {
            $currentDay = $startOfMonth->modify("+$i days");

            $monthlyData[] = [
                'date' => $currentDay,
                'todo' => [],
                'timelog' => [],
            ];
        }


        // Fetch all todos
        $todos = $this->entityManager->getRepository(Todo::class)->findAll();
        $this->assignTodosAndTimelogs($monthlyData, $todos);

        return $this->render('profile/project_and_todo/index.html.twig', [
            'year' => $year,
            'month' => $month,
            'monthlyData' => $monthlyData,
        ]);
    }

    /**
     * Assigns todos and timelogs to the correct days in the dataset.
     */
    private function assignTodosAndTimelogs(array &$data, array $todos): void
    {
        foreach ($todos as $todo) {
            $todoStart = $todo->getDateStart();
            $todoEnd = $todo->getDateEnd();

            if ($todoEnd < $todoStart || trim($todo->getName()) === "") {
                continue;
            }

            foreach ($data as &$day) {
                $dayStart = new DateTime($day['date']->format('Y-m-d'));
                $dayStart->setTime(0, 0, 0);
                $dayEnd = new DateTime($day['date']->format('Y-m-d'));
                $dayEnd->setTime(23, 59, 59);

                // Assign todos if they fall within this day
                if ($todoStart <= $dayEnd && $todoEnd >= $dayStart) {
                    $day['todo'][] = [
                        'id' => $todo->getId(),
                        'title' => $todo->getName(),
                        'start' => $todoStart->format('Y-m-d H:i:s'),
                        'end' => $todoEnd->format('Y-m-d H:i:s'),
                    ];
                }

                // Assign timelogs if they fall within this day
                foreach ($todo->getTimelogs() as $timelog) {
                    $timelogDate = $timelog->getDate();
                    if ($timelogDate >= $dayStart && $timelogDate <= $dayEnd) {
                        $day['timelog'][] = [
                            'id' => $timelog->getId(),
                            'todo_id' => $todo->getId(),
                            'description' => $timelog->getDescription(),
                            'hours' => $timelog->getHours(),
                            'minutes' => $timelog->getMinutes(),
                            'username' => $timelog->getUser() ? $timelog->getUser()->getUsername() : 'Unknown User',
                            'date' => $timelogDate->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
        }
    }


}
