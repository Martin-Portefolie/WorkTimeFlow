<?php
namespace App\Service;

class DateService
{

    public function getWeek(?int $week = null, ?int $year = null) :array
    {
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
        return ['data' => $data];
    }
public function getWeekYear(\DateTimeZone $timezone, ?int $week = null, ?int $year = null): array
{
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

    return ['week' => $week, 'year' => $year];
}
}