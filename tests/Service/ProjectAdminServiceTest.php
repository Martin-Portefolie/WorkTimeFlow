<?php

namespace App\Tests\Service;

use App\Entity\{Project, Client, Rate, Team};
use App\Repository\{ProjectRepository, ClientRepository, RateRepository, TeamRepository};
use App\Service\Admin\ProjectAdminService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectAdminServiceTest extends TestCase
{
    private function service(EntityManagerInterface $em, Client $client, Rate $rate, Team $team): ProjectAdminService
    {
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('find')->willReturnCallback(static fn ($id) => $id === 1 ? $client : null);
        $rates = $this->createStub(RateRepository::class);
        $rates->method('find')->willReturnCallback(static fn ($id) => $id === 1 ? $rate : null);
        $teams = $this->createStub(TeamRepository::class);
        $teams->method('find')->willReturnCallback(static fn ($id) => $id === 1 ? $team : null);
        return new ProjectAdminService($this->createStub(ProjectRepository::class), $clients, $rates, $teams, $em);
    }

    private function data(): array
    {
        return ['name' => ' Website ', 'description' => '', 'client' => '1', 'deadline' => '2027-02-28', 'priority' => 'high', 'rate' => '1', 'teams' => ['1'], 'estimatedBudget' => '1250,50', 'estimatedMinutes' => '90', 'archived' => '0', 'paid' => '1'];
    }

    public function testCreationSavesRelationsAndNormalizedEstimates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $client = new Client(); $rate = new Rate(); $team = new Team();
        $project = new Project();
        $this->service($em, $client, $rate, $team)->save($project, $this->data());
        self::assertSame('Website', $project->getName());
        self::assertSame($client, $project->getClient());
        self::assertSame($rate, $project->getRate());
        self::assertSame('1250.50', $project->getEstimatedBudget());
        self::assertSame(90, $project->getEstimatedMinutes());
        self::assertTrue($team->getProjects()->contains($project));
        self::assertTrue($project->isPaid());
    }

    public function testUpdateClearsOptionalRelationsAndEstimates(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');
        $client = new Client(); $rate = new Rate(); $team = new Team();
        $project = (new Project())->setRate($rate)->addTeam($team)->setEstimatedBudget('12.00')->setEstimatedTime(50);
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, 1);
        $data = array_replace($this->data(), ['rate' => '', 'teams' => [], 'estimatedBudget' => '', 'estimatedMinutes' => '']);
        $this->service($em, $client, $rate, $team)->save($project, $data);
        self::assertNull($project->getRate());
        self::assertNull($project->getEstimatedBudget());
        self::assertNull($project->getEstimatedMinutes());
        self::assertCount(0, $project->getTeams());
        self::assertFalse($team->getProjects()->contains($project));
    }

    public static function invalidValues(): iterable
    {
        yield ['name', 'x'];
        yield ['description', str_repeat('x', 256)];
        yield ['client', '999'];
        yield ['deadline', '2027-02-30'];
        yield ['priority', 'unknown'];
        yield ['rate', '999'];
        yield ['teams', ['999']];
        yield ['teams', '1'];
        yield ['estimatedBudget', '-1'];
        yield ['estimatedBudget', '1.001'];
        yield ['estimatedMinutes', '-1'];
        yield ['estimatedMinutes', '2147483648'];
        yield ['archived', 'yes'];
        yield ['paid', ['1']];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidInputDoesNotMutateExistingProject(string $field, mixed $value): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $em->expects(self::never())->method('persist');
        $client = new Client(); $rate = new Rate(); $team = new Team();
        $project = (new Project())->setName('Original')->setClient($client)->setRate($rate)->addTeam($team);
        $data = $this->data(); $data[$field] = $value;
        try {
            $this->service($em, $client, $rate, $team)->save($project, $data);
            self::fail('Invalid data accepted.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('project.invalid.' . $field, $e->getMessage());
        }
        self::assertSame('Original', $project->getName());
        self::assertSame($rate, $project->getRate());
        self::assertTrue($project->getTeams()->contains($team));
    }
}
