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
        $client->setAdress('N P Josiassens Vej 44E');
        $client->setCity('Grenå');
        $client->setCountry('Danmark');
        $client->setPostalCode('8500');
        $client->setContactPerson('Troels Jensen');
        $client->setContactEmail('troels@fakeemail.dk');
        $client->setContactPhone('+45 12345678');

        $manager->persist($client);
        $manager->flush();
    }
}
