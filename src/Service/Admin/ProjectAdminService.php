<?php

namespace App\Service\Admin;

use App\Entity\Project;
use App\Enum\Priority;
use App\Repository\{ProjectRepository, ClientRepository, RateRepository, TeamRepository};
use Doctrine\ORM\EntityManagerInterface;

final class ProjectAdminService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ClientRepository $clients,
        private readonly RateRepository $rates,
        private readonly TeamRepository $teams,
        private readonly EntityManagerInterface $em,
    ) {}

    public function list(): array
    {
        return $this->projects->findBy([], ['id' => 'DESC'], 100);
    }

    public function choices(): array
    {
        return [
            'clients' => $this->clients->findBy([], ['name' => 'ASC']),
            'rates' => $this->rates->findBy([], ['name' => 'ASC']),
            'teams' => $this->teams->findBy([], ['name' => 'ASC']),
            'priorities' => Priority::cases(),
        ];
    }

    public function save(Project $project, array $data): void
    {
        $name = $this->text($data, 'name');
        $description = $this->text($data, 'description');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 255) $this->invalid('name');
        if (mb_strlen($description) > 255) $this->invalid('description');
        $clientId = $this->text($data, 'client');
        $client = ctype_digit($clientId) ? $this->clients->find((int) $clientId) : null;
        if (!$client) $this->invalid('client');
        $rawDate = $this->text($data, 'deadline');
        $deadline = \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
        if (!$deadline || $deadline->format('Y-m-d') !== $rawDate || $deadline->format('Y') < '1000') $this->invalid('deadline');
        $priority = Priority::tryFrom($this->text($data, 'priority'));
        if (!$priority) $this->invalid('priority');
        $rateId = $this->text($data, 'rate');
        $rate = $rateId !== '' && ctype_digit($rateId) ? $this->rates->find((int) $rateId) : null;
        if ($rateId !== '' && !$rate) $this->invalid('rate');
        $budget = str_replace(',', '.', $this->text($data, 'estimatedBudget'));
        if ($budget !== '' && !preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $budget)) $this->invalid('estimatedBudget');
        $minutes = $this->text($data, 'estimatedMinutes');
        if ($minutes !== '' && (!ctype_digit($minutes) || (float) $minutes > 2147483647)) $this->invalid('estimatedMinutes');
        $archived = $this->text($data, 'archived');
        $paid = $this->text($data, 'paid');
        if (!in_array($archived, ['0', '1'], true)) $this->invalid('archived');
        if (!in_array($paid, ['0', '1'], true)) $this->invalid('paid');
        $teamIds = $data['teams'] ?? [];
        if (!is_array($teamIds)) $this->invalid('teams');
        $teams = [];
        foreach ($teamIds as $id) {
            if (!is_string($id) || !ctype_digit($id)) $this->invalid('teams');
            $team = $this->teams->find((int) $id);
            if (!$team) $this->invalid('teams');
            $teams[$id] = $team;
        }

        // Validate all values and relations before mutating the managed entity.
        $project->setName($name)->setDescription($description === '' ? null : $description)
            ->setClient($client)->setDeadline($deadline)->setPriority($priority)->setRate($rate)
            ->setEstimatedBudget($budget === '' ? null : $budget)
            ->setEstimatedTime($minutes === '' ? null : (int) $minutes)
            ->setArchived($archived === '1')->setIsPaid($paid === '1');
        foreach ($project->getTeams()->toArray() as $team) {
            if (!in_array($team, $teams, true)) $project->removeTeam($team);
        }
        foreach ($teams as $team) $project->addTeam($team);
        if ($project->getId() === null) $this->em->persist($project);
        $this->em->flush();
    }

    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value)) $this->invalid($key);
        return trim($value);
    }

    private function invalid(string $field): never
    {
        throw new \InvalidArgumentException('project.invalid.' . $field);
    }
}
