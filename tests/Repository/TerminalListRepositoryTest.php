<?php

namespace App\Tests\Repository;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Team;
use App\Repository\ProjectRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TerminalListRepositoryTest extends KernelTestCase
{
    public function testTerminalListsPreserveSearchArchiveAndClientFilters(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $prefix = 'Repository-' . bin2hex(random_bytes(8));
            $client = (new Client())->setName($prefix . '-customer')->setContactPerson('Test contact')
                ->setContactEmail('repository@example.test')->setContactPhone('12345678');
            $otherClient = (new Client())->setName($prefix . '-other')->setContactPerson('Other contact')
                ->setContactEmail('other@example.test')->setContactPhone('12345678');
            $activeProject = (new Project())->setName($prefix . '-active')->setClient($client)
                ->setDeadline(new \DateTime('+1 day'));
            $archivedProject = (new Project())->setName($prefix . '-archived')->setClient($client)
                ->setArchived(true)->setDeadline(new \DateTime('+1 day'));
            $team = (new Team())->setName($prefix . '-team');
            $activeProject->addTeam($team);
            foreach ([$client, $otherClient, $activeProject, $archivedProject, $team] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $projectRepository = self::getContainer()->get(ProjectRepository::class);
            self::assertSame([$activeProject], $projectRepository->findForTerminalList($prefix, 50, 'active'));
            self::assertSame([$archivedProject], $projectRepository->findForTerminalList($prefix, 50, 'archived'));
            self::assertCount(2, $projectRepository->findForTerminalList($prefix, 50, 'all'));
            self::assertSame([$activeProject], $projectRepository->findForTerminalList($client->getName(), 50, 'active', $client));
            self::assertSame([], $projectRepository->findForTerminalList($prefix, 50, 'all', $otherClient));
            self::assertSame([$activeProject], $projectRepository->findForTerminalList((string) $activeProject->getId(), 50, 'active', $client));
            self::assertCount(1, $projectRepository->findForTerminalList($prefix, 1, 'all'));

            $teamRepository = self::getContainer()->get(TeamRepository::class);
            self::assertSame([$team], $teamRepository->findForTerminalList($team->getName(), 50));
            self::assertSame([$team], $teamRepository->findForTerminalList($activeProject->getName(), 50));
            self::assertContains($team, $teamRepository->findForTerminalList((string) $team->getId(), 50));
            self::assertSame([], $teamRepository->findForTerminalList($prefix . '-missing', 50));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
