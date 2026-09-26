<?php

namespace App\Tests\Controller;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\Timelog;
use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TimelogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TimelogTest extends WebTestCase
{
    private KernelBrowser $browser;
    private array $ids = [];
    private array $users = [];
    private int $todoId;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $client = (new Client())->setName('Timelog CI')->setContactPhone('123')
            ->setContactEmail('ci@example.test')->setContactPerson('CI');
        $project = (new Project())->setName('Timelog CI')->setClient($client)->setDeadline(new \DateTime('+1 day'));
        $team = (new Team())->setName('Timelog CI');
        $project->addTeam($team);
        $todo = (new Todo())->setName('Shared CI Todo')->setProject($project)
            ->setDateStart(new \DateTime('2026-09-01'))->setDateEnd(new \DateTime('2026-09-30'));
        foreach (['alice', 'bob', 'outsider', 'admin'] as $name) {
            $user = (new User())->setEmail($name . '-' . bin2hex(random_bytes(6)) . '@example.test')
                ->setUsername($name)->setPassword('unused')->setRoles($name === 'admin' ? ['ROLE_ADMIN'] : []);
            if ($name === 'alice' || $name === 'bob') {
                $user->addTeam($team);
            }
            $this->users[$name] = $user;
            $em->persist($user);
        }
        foreach ([$client, $project, $team, $todo] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $this->todoId = $todo->getId();
        $this->ids = ['client' => $client->getId(), 'project' => $project->getId(), 'team' => $team->getId()];
    }

    protected function tearDown(): void
    {
        if ($this->ids !== []) {
            $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
            $db->delete('timelog', ['todo_id' => $this->todoId]);
            $db->delete('todo', ['id' => $this->todoId]);
            $db->delete('user_team', ['team_id' => $this->ids['team']]);
            $db->delete('team_project', ['team_id' => $this->ids['team']]);
            foreach (['team', 'project', 'client'] as $table) {
                $db->delete($table, ['id' => $this->ids[$table]]);
            }
            foreach ($this->users as $user) {
                $db->delete('user', ['id' => $user->getId()]);
            }
        }
        parent::tearDown();
    }

    private function save(string $user, int $hours, string $date = '2026-09-24'): void
    {
        $this->browser->loginUser($this->users[$user]);
        $this->browser->jsonRequest('POST', '/en/profile/save-time', [
            'todoId' => $this->todoId, 'date' => $date, 'hours' => $hours, 'minutes' => 0,
        ]);
    }

    public function testUsersKeepSeparateDailyLogsAndWeeklyTotals(): void
    {
        $this->save('alice', 1);
        self::assertResponseIsSuccessful();
        $this->save('bob', 2);
        self::assertResponseIsSuccessful();
        $this->save('alice', 3);
        self::assertResponseIsSuccessful();
        $repo = self::getContainer()->get(TimelogRepository::class);
        self::assertCount(2, $repo->findBy(['todo' => $this->todoId]));
        foreach (['alice' => 180, 'bob' => 120] as $name => $minutes) {
            $logs = $repo->findTimelogsByUserAndWeek($this->users[$name], 39, 2026);
            self::assertCount(1, $logs);
            self::assertSame($minutes, $logs[0]->getTotalMinutes());
            self::assertSame($this->users[$name]->getId(), $logs[0]->getUser()->getId());
        }
    }

    public function testUnauthorizedAndInvalidWritesAreRejected(): void
    {
        $this->save('outsider', 1);
        self::assertResponseStatusCodeSame(403);
        $this->save('alice', -1);
        self::assertResponseStatusCodeSame(400);
        $this->save('alice', 25);
        self::assertResponseStatusCodeSame(400);
        $this->save('alice', 1, '2026-02-30');
        self::assertResponseStatusCodeSame(400);
        self::assertCount(0, self::getContainer()->get(TimelogRepository::class)->findBy(['todo' => $this->todoId]));
    }

    private function command(string $input): string
    {
        $this->browser->request('POST', '/en/admin/terminal/run', ['input' => $input]);
        self::assertResponseIsSuccessful();
        return $this->browser->getResponse()->getContent();
    }

    public function testAdminCommandsAndConfirmation(): void
    {
        $this->browser->loginUser($this->users['admin']);
        $userId = $this->users['alice']->getId();
        $html = $this->command("timelog.a --user=$userId --todo={$this->todoId} --date=2026-09-24 --minutes=90 --description=Testing");
        self::assertStringContainsString('Timelog created', $html);
        $repo = self::getContainer()->get(TimelogRepository::class);
        $id = $repo->findOneBy(['todo' => $this->todoId])->getId();
        $html = $this->command("timelog.l --user=$userId --todo={$this->todoId} --date=2026-09-24");
        self::assertStringContainsString('data-gui-view="timelogs/list"', $html);
        self::assertStringContainsString('Shared CI Todo', $html);
        self::assertStringContainsString('data-gui-view="timelogs/show"', $this->command("timelog.s $id"));
        self::assertStringContainsString('Timelog updated', $this->command("timelog.u $id --minutes=120"));
        self::assertStringContainsString('2h 0m', $this->command("timelog.s $id"));
        self::assertStringContainsString('Timelog command failed', $this->command("timelog.u $id --minutes=-1"));
        self::assertStringContainsString('2h 0m', $this->command("timelog.s $id"));
        self::assertStringContainsString('data-await="1"', $this->command("timelog.del $id"));
        self::assertStringContainsString('cancelled', $this->command('cancel'));
        self::assertStringContainsString('2h 0m', $this->command("timelog.s $id"));
        $this->command("timelog.del $id");
        self::assertStringContainsString('deleted', $this->command('yes'));
        self::assertStringContainsString('Timelog not found', $this->command("timelog.s $id"));

        $this->browser->loginUser($this->users['alice']);
        $this->browser->request('POST', '/en/admin/terminal/run', ['input' => 'timelog.l']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testWizardCreatesSeparateEntriesAndDailyFormDoesNotOverwriteThem(): void
    {
        $this->browser->loginUser($this->users['admin']);
        foreach ([30, 60] as $minutes) {
            $this->command('timelog.a');
            foreach ([$this->users['alice']->getId(), $this->todoId, '2026-09-24', $minutes, 'Work on shared todo'] as $answer) {
                self::assertStringContainsString('data-await="1"', $this->command((string) $answer));
            }
            self::assertStringContainsString('Timelog created', $this->command('yes'));
        }
        $this->save('alice', 4);
        self::assertResponseStatusCodeSame(409);
        $logs = self::getContainer()->get(TimelogRepository::class)->findBy(['todo' => $this->todoId]);
        self::assertCount(2, $logs);
        self::assertSame(90, array_sum(array_map(static fn (Timelog $log) => $log->getTotalMinutes(), $logs)));
    }
}
