<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Rate;
use App\Entity\Team;
use App\Enum\Priority;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // ✅ Ensure required entities exist
        $client = $manager->getRepository(Client::class)->findOneBy(['name' => 'Viden Djurs']);
        $rate = $manager->getRepository(Rate::class)->findOneBy(['name' => 'Eksammens Rate']);
        $team = $manager->getRepository(Team::class)->findOneBy(['name' => 'Eksammens Team']);

        if (!$client) {
            throw new \LogicException('Client "Viden Djurs" not found. Make sure ClientFixtures runs first.');
        }
        if (!$rate) {
            throw new \LogicException('Rate "Eksammens Rate" not found. Make sure RateFixtures runs first.');
        }
        if (!$team) {
            throw new \LogicException('Team "Eksammens Team" not found. Make sure TeamFixtures runs first.');
        }


        $project = new Project();
        $project->setName('Hest Test Calendar - Multimedie Integrator Svendeprøve');
        $project->setDescription('Et tidsregistrerings system.');
        $project->setClient($client);
        $project->setRate($rate);
        $project->setLastUpdated(new \DateTime());
        $project->setArchived(false);
        $project->setPriority(Priority::MEDIUM);
        $project->setDeadline(new \DateTime('2025-03-08'));
        $project->setEstimatedBudget(0);
        $project->setEstimatedTime(140, 0);

        $manager->persist($project);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ClientFixtures::class,
            RateFixtures::class,
        ];
    }
}
