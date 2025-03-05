<?php

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Rate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class RateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        $company = $this->getReference(CompanyFixtures::COMPANY_REFERENCE, Company::class);
        $rate = new Rate();
        $rate->setCompany($company);
        $rate->setName('Eksammens Rate');
        $rate->setValue('0');
        $manager->persist($rate);
        $manager->flush();

    }
    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
