<?php

namespace App\Command;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-client',
    description: 'Add a short description for your command',
)]
class CreateClientCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        // No arguments needed as we’re hardcoding values for the client
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create ClientFixtures Entity
        $client = new Client();
        $client->setName('Heste-status Aps');
        $client->setContactPhone('12345678');
        $client->setContactEmail('MrHorse@Test.com');
        $client->setContactPerson('Test Hest');

        // Save ClientFixtures to Database
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $io->success('ClientFixtures "Heste-status aps" created with contact person "Mr Horse".');

        return Command::SUCCESS;
    }
}

