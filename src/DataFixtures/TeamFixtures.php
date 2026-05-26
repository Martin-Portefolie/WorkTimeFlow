<?php

namespace App\DataFixtures;

use App\Entity\Team;
use App\Entity\User;
use App\Entity\Project;
use App\DataFixtures\UserFixtures;
use App\DataFixtures\ProjectFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class TeamFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        $user1 = $manager->getRepository(User::class)->findOneBy(['email' => 'm@m.com']);

        if (!$user1 ) {
            throw new \LogicException('Users "m@m.com" must exist before creating a team.');
        }


        $project = $manager->getRepository(Project::class)->findOneBy(['name' => 'Hest Test Calendar - Multimedie Integrator Svendeprøve']);
        if (!$project) {
            throw new \LogicException('Project "Hest Test Calendar" must exist before creating a team.');
        }


        $team = new Team();
        $team->setName('Eksammens Team');

        // Add users to the team
        $team->addUser($user1);

        // Associate the team with the project
        $team->addProject($project);

        $manager->persist($team);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProjectFixtures::class
        ];
    }
}
