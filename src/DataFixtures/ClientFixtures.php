<?php

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ClientFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $client = new Client();
        $client->setName('Viden Djurs');
        $client->setContactPerson('Troels Jensen');
        $client->setContactEmail('troels@fakeemail.com');
        $client->setContactPhone('+45 12345678');
        $manager->persist($client);

        // Create additional clients as needed...

        $manager->flush();
    }
}
