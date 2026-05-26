<?php

namespace App\DataFixtures;

use App\Entity\Todo;
use App\Entity\Project;
use App\DataFixtures\ProjectFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class TodoFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws \Exception
     */
    public function load(ObjectManager $manager): void
    {

        $project = $manager->getRepository(Project::class)->findOneBy(['name' => 'Hest Test Calendar - Multimedie Integrator Svendeprøve']);

        if (!$project) {
            throw new \LogicException('Project "Project Pegasus" must exist before adding Todos.');
        }

        $todos = [
            ['Planlægge forløbet i den første uge', '2025-02-10', '2025-02-12'],
            ['Starte på at skrive Rapporten', '2025-02-10', '2025-02-13'],
            ['Lave en forside med dummy text og billede', '2025-02-10', '2025-02-13'],
            ['Beslutte design, og installere Tailwind', '2025-02-13', '2025-02-14'],
            ['Lave Menuer', '2025-02-13', '2025-02-14'],

            ['Planlægge anden uge', '2025-02-17', '2025-02-18'],
            ['Sætte Authentication op', '2025-02-17', '2025-02-18'],
            ['Sætte Docker op', '2025-02-18', '2025-02-18'],
            ['Lave Admin Siderne', '2025-02-17', '2025-02-21'],

            ['Planlægge 3. uge', '2025-02-24', '2025-02-25'],
            ['Lave Profile siderne', '2025-02-24', '2025-02-28'],
            ['Ændre designet til gråt', '2025-02-25', '2025-02-28'],

            ['Planlægge 4. uge', '2025-03-03', '2025-03-04'],
            ['Opdatere mangler og fejl', '2025-03-03', '2025-03-05'],
            ['Debugging', '2025-03-03', '2025-03-05'],
            ['Skrive rapport', '2025-03-06', '2025-03-07'],
            ['Aflevere', '2025-03-08', '2025-03-08'],
        ];

        foreach ($todos as [$name, $start, $end]) {
            $todo = new Todo();
            $todo->setName($name);
            $todo->setDateStart(new \DateTime($start));
            $todo->setDateEnd(new \DateTime($end));
            $todo->setProject($project);

            $manager->persist($todo);
        }


        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProjectFixtures::class,
        ];
    }
}
