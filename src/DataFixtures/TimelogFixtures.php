<?php

namespace App\DataFixtures;

use App\Entity\Timelog;
use App\Entity\Todo;
use App\Entity\User;
use App\DataFixtures\TodoFixtures;
use App\DataFixtures\UserFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class TimelogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        $user = $manager->getRepository(User::class)->find(2);
        if (!$user) {
            throw new \LogicException('User with ID 2 must exist before logging time.');
        }




        $timelogs = [
            ['todo_id' => 1, 'total_minutes' => 120, 'date' => '2025-02-10', 'description' => null],
            ['todo_id' => 2, 'total_minutes' => 300, 'date' => '2025-02-10', 'description' => null],
            ['todo_id' => 2, 'total_minutes' => 180, 'date' => '2025-02-11', 'description' => null],
            ['todo_id' => 5, 'total_minutes' => 240, 'date' => '2025-02-11', 'description' => null],
            ['todo_id' => 3, 'total_minutes' => 360, 'date' => '2025-02-12', 'description' => null],
            ['todo_id' => 2, 'total_minutes' => 60, 'date' => '2025-02-12', 'description' => null],
            ['todo_id' => 6, 'total_minutes' => 240, 'date' => '2025-02-13', 'description' => null],
            ['todo_id' => 2, 'total_minutes' => 180, 'date' => '2025-02-13', 'description' => null],
            ['todo_id' => 6, 'total_minutes' => 120, 'date' => '2025-02-16', 'description' => null],
            ['todo_id' => 3, 'total_minutes' => 300, 'date' => '2025-02-16', 'description' => null],
            ['todo_id' => 6, 'total_minutes' => 60, 'date' => '2025-02-17', 'description' => null],
            ['todo_id' => 7, 'total_minutes' => 180, 'date' => '2025-02-17', 'description' => null],
            ['todo_id' => 8, 'total_minutes' => 180, 'date' => '2025-02-17', 'description' => null],
            ['todo_id' => 7, 'total_minutes' => 120, 'date' => '2025-02-18', 'description' => null],
            ['todo_id' => 8, 'total_minutes' => 60, 'date' => '2025-02-18', 'description' => null],
            ['todo_id' => 9, 'total_minutes' => 240, 'date' => '2025-02-18', 'description' => null],
            ['todo_id' => 9, 'total_minutes' => 420, 'date' => '2025-02-19', 'description' => null],
            ['todo_id' => 9, 'total_minutes' => 420, 'date' => '2025-02-20', 'description' => null],
            ['todo_id' => 9, 'total_minutes' => 420, 'date' => '2025-02-21', 'description' => null],
            ['todo_id' => 10, 'total_minutes' => 120, 'date' => '2025-02-24', 'description' => null],
            ['todo_id' => 11, 'total_minutes' => 420, 'date' => '2025-02-25', 'description' => null],
            ['todo_id' => 11, 'total_minutes' => 420, 'date' => '2025-02-26', 'description' => null],
            ['todo_id' => 12, 'total_minutes' => 420, 'date' => '2025-02-27', 'description' => null],
            ['todo_id' => 12, 'total_minutes' => 120, 'date' => '2025-02-28', 'description' => null],
            ['todo_id' => 11, 'total_minutes' => 300, 'date' => '2025-02-28', 'description' => null],
            ['todo_id' => 12, 'total_minutes' => 300, 'date' => '2025-02-24', 'description' => null],
            ['todo_id' => 13, 'total_minutes' => 120, 'date' => '2025-03-03', 'description' => null],
            ['todo_id' => 14, 'total_minutes' => 300, 'date' => '2025-03-03', 'description' => null],
            ['todo_id' => 14, 'total_minutes' => 420, 'date' => '2025-03-04', 'description' => null],
            ['todo_id' => 14, 'total_minutes' => 300, 'date' => '2025-03-05', 'description' => null],
            ['todo_id' => 15, 'total_minutes' => 300, 'date' => '2025-03-06', 'description' => null],
            ['todo_id' => 16, 'total_minutes' => 120, 'date' => '2025-03-06', 'description' => null],
            ['todo_id' => 16, 'total_minutes' => 120, 'date' => '2025-03-05', 'description' => null],
            ['todo_id' => 17, 'total_minutes' => 300, 'date' => '2025-03-07', 'description' => null],
        ];

        foreach ($timelogs as $data) {

            $todo = $manager->getRepository(Todo::class)->find($data['todo_id']);

            if (!$todo) {
                throw new \LogicException("Todo with ID {$data['todo_id']} not found.");
            }


            $timelog = new Timelog();
            $timelog->setUser($user);
            $timelog->setTodo($todo);
            $timelog->setTotalMinutes($data['total_minutes']);
            $timelog->setDate(new \DateTime($data['date']));
            $timelog->setDescription($data['description']);

            $manager->persist($timelog);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            TodoFixtures::class,
        ];
    }
}
