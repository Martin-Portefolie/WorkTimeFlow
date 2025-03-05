<?php

namespace App\DataFixtures;

use App\Entity\Company;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CompanyFixtures extends Fixture
{
    public const COMPANY_REFERENCE = 'our-company';

    public function load(ObjectManager $manager): void
    {
            $company = new Company();
            $manager->persist($company);
            $manager->flush();

        $this->addReference(self::COMPANY_REFERENCE, $company);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
