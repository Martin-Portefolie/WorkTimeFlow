<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Rate;
use App\Entity\Team;
use App\Enum\Priority;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Repository\RateRepository;
use App\Repository\TeamRepository;
use App\Service\Terminal\DateInputParser;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalProjectService
{
    private const string VIEW = 'terminals/admin/terminal_commands/projects.html.twig';
    private const string WIZARD_MODE = 'projects:add';

    private const array ADD_STEPS = [
        [
            'key' => 'client',
            'label' => 'Client id or exact name',
            'example' => '1 or Acme ApS',
        ],
        [
            'key' => 'teams',
            'label' => 'Teams, comma separated. Leave empty to skip',
            'example' => '1, 2 or Design Team, Dev Team',
        ],
        [
            'key' => 'name',
            'label' => 'Project name',
            'example' => 'Website redesign',
        ],
        [
            'key' => 'description',
            'label' => 'Short description. Leave empty to skip',
            'example' => 'Build new client website.',
        ],
        [
            'key' => 'priority',
            'label' => 'Priority: 1=low, 2=medium, 3=high, 4=critical. Leave empty for medium',
            'example' => '2',
        ],
        [
            'key' => 'deadline',
            'label' => 'Deadline. Leave empty for +30 days',
            'example' => '30, mon-2, next fri or 08/03/2026',
        ],
        [
            'key' => 'estimated',
            'label' => 'Estimated time. Leave empty to skip. Plain numbers are minutes; use h for hours.',
            'example' => '140h, 2h30m, 90m or 90',
        ],
        [
            'key' => 'budget',
            'label' => 'Estimated budget. Leave empty to skip',
            'example' => '25000.00',
        ],
        [
            'key' => 'rate',
            'label' => 'Rate id or exact name. Leave empty to skip',
            'example' => '1 or Standard',
        ],
    ];

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ClientRepository $clientRepository,
        private readonly RateRepository $rateRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TerminalStateService $state,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly DateInputParser $dateInputParser,
    ) {
    }

    // -----------------------------------------------------
    // List Projects
    // -----------------------------------------------------

    public function list(array $flags = []): array
    {
        $q = isset($flags['q']) ? trim((string) $flags['q']) : null;
        $limit = isset($flags['limit']) ? max(1, (int) $flags['limit']) : 50;
        $archivedFlag = strtolower((string) ($flags['archived'] ?? 'active'));

        if (!in_array($archivedFlag, ['active', 'archived', 'all'], true)) {
            $archivedFlag = 'active';
        }

        $qb = $this->projectRepository->createQueryBuilder('p')
            ->leftJoin('p.client', 'c')->addSelect('c')
            ->leftJoin('p.teams', 't')->addSelect('t')
            ->leftJoin('p.rate', 'r')->addSelect('r')
            ->orderBy('p.lastUpdated', 'DESC')
            ->setMaxResults($limit);

        if ($archivedFlag === 'active') {
            $qb->andWhere('p.isArchived = :archived')->setParameter('archived', false);
        }

        if ($archivedFlag === 'archived') {
            $qb->andWhere('p.isArchived = :archived')->setParameter('archived', true);
        }

        if ($q) {
            if (ctype_digit($q)) {
                $qb->andWhere('p.id = :id OR LOWER(p.name) LIKE :q OR LOWER(c.name) LIKE :q')
                    ->setParameter('id', (int) $q)
                    ->setParameter('q', '%' . mb_strtolower($q) . '%');
            } else {
                $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(c.name) LIKE :q')
                    ->setParameter('q', '%' . mb_strtolower($q) . '%');
            }
        }

        if (isset($flags['client'])) {
            $client = $this->resolveClient((string) $flags['client']);

            if (!$client) {
                return $this->error('Client not found', sprintf('No client found for "%s".', $flags['client']));
            }

            $qb->andWhere('p.client = :client')->setParameter('client', $client);
        }

        $rows = array_map(
            fn (Project $project) => $this->projectListRow($project),
            $qb->getQuery()->getResult(),
        );

        usort($rows, static function (array $a, array $b): int {
            if ($a['isOverrun'] !== $b['isOverrun']) {
                return $a['isOverrun'] ? -1 : 1;
            }

            return ($b['lastUpdatedTimestamp'] ?? 0) <=> ($a['lastUpdatedTimestamp'] ?? 0);
        });

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'list',
                'resource' => 'projects',
                'title' => 'Projects',
                'description' => 'Projects available to admin commands.',
                'columns' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'name', 'label' => 'Project'],
                    ['key' => 'clientName', 'label' => 'Client'],
                    ['key' => 'priority', 'label' => 'Priority'],
                    ['key' => 'deadlineFormatted', 'label' => 'Deadline'],
                    ['key' => 'estimatedLabel', 'label' => 'Est.'],
                    ['key' => 'totalUsedLabel', 'label' => 'Used'],
                    ['key' => 'remainingLabel', 'label' => 'Remaining'],
                    ['key' => 'teamsCount', 'label' => 'Teams'],
                    ['key' => 'rateName', 'label' => 'Rate'],
                ],
                'rows' => $rows,
                'projects' => $rows,
                'search' => $q,
                'archivedFlag' => $archivedFlag,
                'shownCount' => count($rows),
                'limit' => $limit,
            ],
        ];
    }

    // -----------------------------------------------------
    // Show Project
    // -----------------------------------------------------

    public function show(array $args = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing project identifier',
                'Usage: projects:show <id|name>',
            );
        }

        $project = $this->resolveProject((string) $identifier);

        if (!$project) {
            return $this->error(
                'Project not found',
                sprintf('No project found for "%s".', $identifier),
            );
        }

        return $this->projectShowPayload('Project', $project);
    }

    // -----------------------------------------------------
    // Add Project
    // -----------------------------------------------------

    public function add(array $flags = []): array
    {
        if ($flags !== []) {
            $result = $this->createProjectFromData([
                'name' => $flags['name'] ?? null,
                'description' => $flags['description'] ?? null,
                'client' => $flags['client'] ?? null,
                'teams' => $flags['teams'] ?? null,
                'priority' => $flags['priority'] ?? null,
                'deadline' => $flags['deadline'] ?? null,
                'estimated' => $flags['estimated'] ?? $flags['estimated-minutes'] ?? null,
                'budget' => $flags['budget'] ?? $flags['estimated-budget'] ?? null,
                'rate' => $flags['rate'] ?? null,
            ]);

            if (!$result['success']) {
                return $this->error('Project could not be created', $result['error']);
            }

            return $this->projectShowPayload('Project created', $result['project']);
        }

        $userId = $this->currentUserId();

        $this->state->set($userId, [
            'mode' => self::WIZARD_MODE,
            'step' => 1,
            'data' => [],
        ]);

        return $this->buildStepView(1);
    }

    // -----------------------------------------------------
    // Update Project
    // -----------------------------------------------------

    public function update(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing project identifier',
                'Usage: projects:update <id|name> --name= --client= --priority= --deadline= --estimated= --budget= --rate= --archived= --paid=',
            );
        }

        $project = $this->resolveProject((string) $identifier);

        if (!$project) {
            return $this->error('Project not found', sprintf('No project found for "%s".', $identifier));
        }

        $changed = [];

        if (isset($flags['name'])) {
            $name = trim((string) $flags['name']);

            if (mb_strlen($name) < 2) {
                return $this->error('Invalid project name', 'Project name must be at least 2 characters.');
            }

            $project->setName($name);
            $changed[] = 'name';
        }

        if (array_key_exists('description', $flags)) {
            $project->setDescription($this->nullableString($flags['description']));
            $changed[] = 'description';
        }

        if (isset($flags['client'])) {
            $client = $this->resolveClient((string) $flags['client']);

            if (!$client) {
                return $this->error('Client not found', sprintf('No client found for "%s".', $flags['client']));
            }

            $project->setClient($client);
            $changed[] = 'client';
        }

        if (isset($flags['priority'])) {
            $priority = $this->parsePriority((string) $flags['priority']);

            if (!$priority) {
                return $this->error('Invalid priority', 'Use 1=low, 2=medium, 3=high, 4=critical or low|medium|high|critical.');
            }

            $project->setPriority($priority);
            $changed[] = 'priority';
        }

        if (isset($flags['deadline'])) {
            $deadline = $this->dateInputParser->parseFlexibleDate((string) $flags['deadline'], null, null);

            if (!$deadline) {
                return $this->error('Invalid deadline', 'Use days, date, weekday, or next weekday.');
            }

            $project->setDeadline(
                \DateTime::createFromImmutable($deadline)
            );
            $changed[] = 'deadline';
        }

        $estimatedRaw = $flags['estimated'] ?? $flags['estimated-minutes'] ?? null;
        if ($estimatedRaw !== null) {
            $project->setEstimatedTime($estimatedRaw === '' ? null : $this->parseEstimatedTimeOrFail((string) $estimatedRaw));
            $changed[] = 'estimated time';
        }

        $budgetRaw = $flags['budget'] ?? $flags['estimated-budget'] ?? null;
        if ($budgetRaw !== null) {
            $project->setEstimatedBudget($budgetRaw === '' ? null : $this->parseBudget((string) $budgetRaw));
            $changed[] = 'budget';
        }

        if (isset($flags['rate'])) {
            if ((string) $flags['rate'] === '') {
                $project->setRate(null);
            } else {
                $rate = $this->resolveRate((string) $flags['rate']);

                if (!$rate) {
                    return $this->error('Rate not found', sprintf('No rate found for "%s".', $flags['rate']));
                }

                $project->setRate($rate);
            }

            $changed[] = 'rate';
        }

        if (isset($flags['teams'])) {
            $teams = trim((string) $flags['teams']) === '' ? [] : $this->resolveTeams((string) $flags['teams']);

            foreach ($project->getTeams()->toArray() as $team) {
                $project->removeTeam($team);
            }

            foreach ($teams as $team) {
                $project->addTeam($team);
            }

            $changed[] = 'teams';
        }

        if (isset($flags['archived']) || isset($flags['is-archived'])) {
            $project->setArchived($this->parseBool((string) ($flags['archived'] ?? $flags['is-archived'])));
            $changed[] = 'archived';
        }

        if (isset($flags['paid']) || isset($flags['is-paid'])) {
            $project->setIsPaid($this->parseBool((string) ($flags['paid'] ?? $flags['is-paid'])));
            $changed[] = 'paid';
        }

        if ($changed === []) {
            return $this->error(
                'Nothing to update',
                'Use one or more flags: --name= --description= --client= --priority= --deadline= --estimated= --budget= --rate= --teams= --archived= --paid=',
            );
        }

        $this->em->flush();

        return $this->projectShowPayload('Project updated', $project);
    }

    // -----------------------------------------------------
    // Delete Project
    // -----------------------------------------------------

    public function delete(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error('Missing project identifier', 'Usage: projects:delete <id|name> --force');
        }

        if (!isset($flags['force'])) {
            return $this->error('Delete requires confirmation', sprintf('Run: projects:delete %s --force', $identifier));
        }

        $project = $this->resolveProject((string) $identifier);

        if (!$project) {
            return $this->error('Project not found', sprintf('No project found for "%s".', $identifier));
        }

        $name = $project->getName();

        $this->em->remove($project);
        $this->em->flush();

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'delete',
                'title' => 'Project deleted',
                'message' => sprintf('Project "%s" was deleted.', $name),
            ],
        ];
    }

    // -----------------------------------------------------
    // Interactive Wizard Handler
    // -----------------------------------------------------

    public function handleInteractive(string $raw): ?array
    {
        $userId = $this->currentUserId();
        $state = $this->state->get($userId);

        if (!$state || ($state['mode'] ?? null) !== self::WIZARD_MODE) {
            return null;
        }

        $raw = trim($raw);
        $lower = mb_strtolower($raw);

        if ($lower === 'cancel') {
            $this->state->clear($userId);

            return [
                'success' => false,
                'output' => 'Project creation cancelled.',
                'await' => false,
            ];
        }

        if (in_array($lower, ['p.a', 'projects:add'], true)) {
            $this->state->clear($userId);

            return $this->add();
        }

        return $this->handleAddWizard($userId, $state, $raw);
    }

    // -----------------------------------------------------
    // Project Add Wizard
    // -----------------------------------------------------

    private function handleAddWizard(string $userId, array $state, string $input): array
    {
        $step = (int) ($state['step'] ?? 1);
        $data = $state['data'] ?? [];
        $confirmStep = count(self::ADD_STEPS) + 1;

        // -----------------------------------------------------
        // Confirm Project Creation
        // -----------------------------------------------------

        if ($step === $confirmStep) {
            if (!in_array(mb_strtolower(trim($input)), ['yes', 'y'], true)) {
                $this->state->clear($userId);

                return [
                    'success' => false,
                    'output' => 'Project creation aborted.',
                ];
            }

            $result = $this->createProjectFromData($data);
            $this->state->clear($userId);

            if (!$result['success']) {
                return $this->error('Project could not be created', $result['error']);
            }

            return $this->projectShowPayload('Project created', $result['project']);
        }

        $stepDefinition = self::ADD_STEPS[$step - 1] ?? null;

        if (!$stepDefinition) {
            $this->state->clear($userId);

            return [
                'success' => false,
                'output' => 'Wizard state became invalid.',
            ];
        }

        $key = $stepDefinition['key'];
        $value = trim($input);
        $error = null;

        switch ($key) {
            case 'client':
                if ($value === '') {
                    $error = 'Client is required.';
                    break;
                }

                $client = $this->resolveClient($value);
                if (!$client) {
                    $error = 'Client not found. Use an existing id or exact name.';
                    break;
                }

                $data['client'] = (string) $client->getId();
                $data['clientName'] = $client->getName();
                break;

            case 'teams':
                if ($value === '') {
                    $data['teams'] = '';
                    $data['teamNames'] = [];
                    break;
                }

                $teams = $this->resolveTeams($value);
                if ($teams === []) {
                    $error = 'No teams matched that input. Use existing ids or exact names, comma separated.';
                    break;
                }

                $data['teams'] = implode(',', array_map(static fn (Team $team) => (string) $team->getId(), $teams));
                $data['teamNames'] = array_map(static fn (Team $team) => (string) $team->getName(), $teams);
                break;

            case 'name':
                if (mb_strlen($value) < 2) {
                    $error = 'Project name must be at least 2 characters.';
                    break;
                }

                $data['name'] = $value;
                break;

            case 'description':
                $data['description'] = $value;
                break;

            case 'priority':
                if ($value === '') {
                    $data['priority'] = Priority::MEDIUM->value;
                    break;
                }

                $priority = $this->parsePriority($value);
                if (!$priority) {
                    $error = 'Invalid priority. Use 1=low, 2=medium, 3=high, 4=critical or low|medium|high|critical.';
                    break;
                }

                $data['priority'] = $priority->value;
                break;

            case 'deadline':
                if ($value !== '' && !$this->dateInputParser->parseFlexibleDate($value, null, 30)) {
                    $error = 'Invalid deadline. Use days, date, weekday, or next weekday.';
                    break;
                }

                $data['deadline'] = $value;
                break;

            case 'estimated':
                if ($value !== '' && $this->parseEstimatedTime($value) === null) {
                    $error = 'Invalid estimated time. Use 140h, 2h30m, 90m or plain minutes.';
                    break;
                }

                $data['estimated'] = $value;
                break;

            case 'budget':
                $data['budget'] = $value;
                break;

            case 'rate':
                if ($value === '') {
                    $data['rate'] = '';
                    $data['rateName'] = null;
                    break;
                }

                $rate = $this->resolveRate($value);
                if (!$rate) {
                    $error = 'Rate not found. Use an existing id or exact name.';
                    break;
                }

                $data['rate'] = (string) $rate->getId();
                $data['rateName'] = $rate->getName();
                break;
        }

        if ($error) {
            $this->saveWizardStep($userId, $step, $data);

            return $this->buildStepView($step, $error);
        }

        if ($step < count(self::ADD_STEPS)) {
            $this->saveWizardStep($userId, $step + 1, $data);

            return $this->buildStepView($step + 1);
        }

        return $this->buildProjectConfirmView($userId, $data);
    }

    // -----------------------------------------------------
    // Build Project Confirm View
    // -----------------------------------------------------

    private function buildProjectConfirmView(string $userId, array $data): array
    {
        $confirmStep = count(self::ADD_STEPS) + 1;

        $deadline = $this->dateInputParser->parseFlexibleDate(
            (string) ($data['deadline'] ?? ''),
            null,
            30,
        );

        $deadlineWarning = $deadline !== null && $deadline < new \DateTimeImmutable('today');

        $estimatedRaw = trim((string) ($data['estimated'] ?? ''));
        $estimatedMinutes = $estimatedRaw === '' ? null : $this->parseEstimatedTime($estimatedRaw);
        $estimatedLabel = $estimatedMinutes !== null
            ? sprintf('%s (%s)', $estimatedRaw, $this->formatMinutes($estimatedMinutes))
            : '—';

        $fields = [
            ['label' => 'Client', 'value' => $data['clientName'] ?? '—'],
            ['label' => 'Teams', 'value' => !empty($data['teamNames']) ? implode(', ', $data['teamNames']) : 'none'],
            ['label' => 'Name', 'value' => $data['name'] ?? '—'],
            ['label' => 'Description', 'value' => ($data['description'] ?? '') !== '' ? $data['description'] : '—'],
            ['label' => 'Priority', 'value' => $data['priority'] ?? Priority::MEDIUM->value],
            ['label' => 'Deadline', 'value' => ($data['deadline'] ?? '') !== '' ? $data['deadline'] : 'default (+30 days)'],
        ];

        if ($deadlineWarning) {
            $fields[] = [
                'label' => 'Warning',
                'value' => '⚠ Deadline is in the past',
            ];
        }

        $fields[] = [
            'label' => 'Estimated time',
            'value' => $estimatedLabel,
        ];
        $fields[] = [
            'label' => 'Estimated note',
            'value' => 'Plain numbers are interpreted as minutes. Use h for hours, e.g. 140h or 2h30m.',
        ];
        $fields[] = [
            'label' => 'Estimated budget',
            'value' => ($data['budget'] ?? '') !== '' ? $data['budget'] : '—',
        ];
        $fields[] = [
            'label' => 'Rate',
            'value' => $data['rateName'] ?? '—',
        ];

        $this->saveWizardStep($userId, $confirmStep, $data);

        return [
            'success' => true,
            'await' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'confirm',
                'title' => sprintf('Step %d of %d — Confirm project creation', $confirmStep, $confirmStep),
                'description' => 'Type "yes" or "y" to confirm. Any other input aborts.',
                'fields' => $fields,
            ],
        ];
    }

    // -----------------------------------------------------
    // Create Project From Data
    // -----------------------------------------------------

    /** @return array{success: bool, project?: Project, error?: string} */
    private function createProjectFromData(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if (mb_strlen($name) < 2) {
            return ['success' => false, 'error' => 'Project name is required and must be at least 2 characters.'];
        }

        $client = $this->resolveClient((string) ($data['client'] ?? ''));

        if (!$client) {
            return ['success' => false, 'error' => 'Client is required and must exist.'];
        }

        $priority = trim((string) ($data['priority'] ?? '')) === ''
            ? Priority::MEDIUM
            : $this->parsePriority((string) $data['priority']);

        if (!$priority) {
            return ['success' => false, 'error' => 'Invalid priority.'];
        }

        $deadline = $this->dateInputParser->parseFlexibleDate((string) ($data['deadline'] ?? ''), null, 30);

        if (!$deadline) {
            return ['success' => false, 'error' => 'Invalid deadline format.'];
        }

        $estimatedRaw = trim((string) ($data['estimated'] ?? ''));
        $estimatedMinutes = $estimatedRaw === '' ? null : $this->parseEstimatedTime($estimatedRaw);

        if ($estimatedRaw !== '' && $estimatedMinutes === null) {
            return ['success' => false, 'error' => 'Invalid estimated time.'];
        }

        $budgetRaw = trim((string) ($data['budget'] ?? ''));
        $budget = $budgetRaw === '' ? null : $this->parseBudget($budgetRaw);

        $rateRaw = trim((string) ($data['rate'] ?? ''));
        $rate = $rateRaw === '' ? null : $this->resolveRate($rateRaw);

        if ($rateRaw !== '' && !$rate) {
            return ['success' => false, 'error' => 'Rate not found.'];
        }

        $teamsRaw = trim((string) ($data['teams'] ?? ''));
        $teams = $teamsRaw === '' ? [] : $this->resolveTeams($teamsRaw);

        $project = new Project();
        $project->setName($name);
        $project->setDescription($this->nullableString($data['description'] ?? null));
        $project->setClient($client);
        $project->setPriority($priority);
        $project->setDeadline(
            \DateTime::createFromImmutable($deadline)
        );
        $project->setEstimatedTime($estimatedMinutes);
        $project->setEstimatedBudget($budget);
        $project->setRate($rate);

        foreach ($teams as $team) {
            $project->addTeam($team);
        }

        $this->em->persist($project);
        $this->em->flush();

        return ['success' => true, 'project' => $project];
    }

    // -----------------------------------------------------
    // Project Show Payload
    // -----------------------------------------------------

    private function projectShowPayload(string $title, Project $project): array
    {
        $summary = $this->buildSummary($project);

        $teams = array_map(
            static fn (Team $team): array => [
                'id' => $team->getId(),
                'name' => $team->getName(),
            ],
            $project->getTeams()->toArray(),
        );

        $todos = array_map(
            fn ($todo): array => [
                'id' => $todo->getId(),
                'name' => $todo->getName(),
                'status' => $todo->getStatus()->name,
                'totalMinutes' => $this->todoTotalMinutes($todo),
                'totalLabel' => $this->formatMinutes($this->todoTotalMinutes($todo)) ?? '0h 0m',
                'timelogsCount' => $todo->getTimelogs()->count(),
            ],
            $project->getTodos()->toArray(),
        );

        $fields = [
            ['label' => 'ID', 'value' => $project->getId()],
            ['label' => 'Name', 'value' => $project->getName()],
            ['label' => 'Client', 'value' => sprintf('#%s %s', $summary['clientId'] ?? '—', $summary['clientName'])],
            ['label' => 'Description', 'value' => $project->getDescription() ?: '—'],
            ['label' => 'Priority', 'value' => $summary['priority']],
            ['label' => 'Deadline', 'value' => $summary['deadlineFormatted'] ?: '—'],
            ['label' => 'Estimated', 'value' => $summary['estimatedLabel'] ?: '—'],
            ['label' => 'Used', 'value' => $summary['totalUsedLabel']],
            ['label' => 'Remaining', 'value' => $summary['remainingLabel'] ?: '—'],
            ['label' => 'Budget', 'value' => $project->getEstimatedBudget() ?: '—'],
            ['label' => 'Rate', 'value' => $summary['rateName'] ? sprintf('%s (%s)', $summary['rateName'], $summary['rateValue']) : '—'],
            ['label' => 'Teams', 'value' => sprintf('(%d)', count($teams))],
            ['label' => 'Todos', 'value' => count($todos)],
            ['label' => 'Archived', 'value' => $summary['isArchived'] ? 'yes' : 'no'],
            ['label' => 'Paid', 'value' => $summary['isPaid'] ? 'yes' : 'no'],
            ['label' => 'Last updated', 'value' => $summary['lastUpdatedFormatted'] ?: '—'],
        ];

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'show',
                'resource' => 'projects',
                'title' => $title,
                'fields' => $fields,
                'project' => $project,
                'summary' => $summary,
                'teams' => $teams,
                'todos' => $todos,
            ],
        ];
    }


    // -----------------------------------------------------
    // Todo Total Minutes
    // -----------------------------------------------------

    private function todoTotalMinutes($todo): int
    {
        $total = 0;

        foreach ($todo->getTimelogs() as $timelog) {
            $total += $timelog->getTotalMinutes();
        }

        return $total;
    }

    // -----------------------------------------------------
    // Build Summary
    // -----------------------------------------------------

    private function buildSummary(Project $project): array
    {
        $deadline = $project->getDeadline();
        $totalMinutes = $project->getTotalMinutesUsed();
        $estimatedMinutes = $project->getEstimatedMinutes();
        $remainingMinutes = $estimatedMinutes === null ? null : $estimatedMinutes - $totalMinutes;
        $isOverrun = $remainingMinutes !== null && $remainingMinutes < 0;

        return [
            'deadlineFormatted' => $deadline?->format('d/m/Y'),
            'deadlineExpired' => $deadline !== null && $deadline < new \DateTimeImmutable('now'),
            'priority' => $project->getPriority()?->name ?? 'MEDIUM',
            'estimatedMinutes' => $estimatedMinutes,
            'estimatedLabel' => $this->formatMinutes($estimatedMinutes),
            'estimatedBudgetLabel' => $project->getEstimatedBudget()
                ? number_format((float) $project->getEstimatedBudget(), 2, ',', '.')
                : '0,00',
            'totalMinutesUsed' => $totalMinutes,
            'totalUsedLabel' => $this->formatMinutes($totalMinutes) ?? '0h 0m',
            'remainingMinutes' => $remainingMinutes,
            'remainingLabel' => $remainingMinutes !== null ? $this->formatMinutes(max(0, $remainingMinutes)) : null,
            'isOverrun' => $isOverrun,
            'clientName' => $project->getClient()?->getName() ?? '—',
            'clientId' => $project->getClient()?->getId(),
            'rateName' => $project->getRate()?->getName(),
            'rateValue' => $project->getRate()?->getValue(),
            'teamsCount' => $project->getTeams()->count(),
            'todosCount' => $project->getTodos()->count(),
            'isArchived' => $project->isArchived(),
            'isPaid' => $project->isPaid() ?? false,
            'lastUpdated' => $project->getLastUpdated(),
            'lastUpdatedFormatted' => $project->getLastUpdated()?->format('d/m/Y H:i'),
        ];
    }

    // -----------------------------------------------------
    // Project List Row
    // -----------------------------------------------------

    private function projectListRow(Project $project): array
    {
        $summary = $this->buildSummary($project);

        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'clientName' => $summary['clientName'],
            'priority' => $summary['priority'],
            'deadlineFormatted' => $summary['deadlineFormatted'],
            'deadlineExpired' => $summary['deadlineExpired'],
            'estimatedMinutes' => $summary['estimatedMinutes'],
            'estimatedLabel' => $summary['estimatedLabel'] ?: '—',
            'totalMinutesUsed' => $summary['totalMinutesUsed'],
            'totalUsedLabel' => $summary['totalUsedLabel'],
            'remainingMinutes' => $summary['remainingMinutes'],
            'remainingLabel' => $summary['remainingLabel'] ?: '—',
            'isOverrun' => $summary['isOverrun'],
            'teamsCount' => $summary['teamsCount'],
            'rateName' => $summary['rateName'] ?: '—',
            'isArchived' => $summary['isArchived'],
            'isPaid' => $summary['isPaid'],
            'lastUpdated' => $summary['lastUpdated'],
            'lastUpdatedTimestamp' => $summary['lastUpdated'] instanceof \DateTimeInterface ? $summary['lastUpdated']->getTimestamp() : 0,
        ];
    }

    // -----------------------------------------------------
    // Prompt Helper
    // -----------------------------------------------------

    private function buildStepView(int $step, ?string $error = null): array
    {
        $stepDefinition = self::ADD_STEPS[$step - 1];
        $vars = [
            'step' => $step,
            'total' => count(self::ADD_STEPS) + 1,
            'label' => $stepDefinition['label'],
            'example' => $stepDefinition['example'] ?? null,
            'error' => $error,
        ];

        if ($stepDefinition['key'] === 'client') {
            $vars['clientsPreview'] = $this->getClientPreview();
        }

        if ($stepDefinition['key'] === 'teams') {
            $vars['teamsPreview'] = $this->getTeamPreview();
        }

        if ($stepDefinition['key'] === 'rate') {
            $vars['ratesPreview'] = $this->getRatePreview();
        }

        return [
            'success' => $error === null,
            'await' => true,
            'view' => 'terminals/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars' => $vars,
        ];
    }

    // -----------------------------------------------------
    // Error Helper
    // -----------------------------------------------------

    private function error(string $title, string $message): array
    {
        return [
            'success' => false,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'error',
                'title' => $title,
                'message' => $message,
            ],
        ];
    }

    // -----------------------------------------------------
    // Save Wizard Step
    // -----------------------------------------------------

    private function saveWizardStep(string $userId, int $step, array $data): void
    {
        $this->state->set($userId, [
            'mode' => self::WIZARD_MODE,
            'step' => $step,
            'data' => $data,
        ]);
    }

    // -----------------------------------------------------
    // Current User Id
    // -----------------------------------------------------

    private function currentUserId(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'guest';
    }

    // -----------------------------------------------------
    // Resolve Project
    // -----------------------------------------------------

    private function resolveProject(string $raw): ?Project
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $project = $this->projectRepository->find((int) $raw);
            if ($project) {
                return $project;
            }
        }

        return $this->projectRepository->findOneBy(['name' => $raw]);
    }

    // -----------------------------------------------------
    // Resolve Client
    // -----------------------------------------------------

    private function resolveClient(string $raw): ?Client
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (method_exists($this->clientRepository, 'findOneByIdNameOrEmail')) {
            return $this->clientRepository->findOneByIdNameOrEmail($raw);
        }

        if (ctype_digit($raw)) {
            return $this->clientRepository->find((int) $raw);
        }

        return $this->clientRepository->findOneBy(['name' => $raw]);
    }

    // -----------------------------------------------------
    // Resolve Rate
    // -----------------------------------------------------

    private function resolveRate(string $raw): ?Rate
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $rate = $this->rateRepository->find((int) $raw);
            if ($rate) {
                return $rate;
            }
        }

        return $this->rateRepository->findOneBy(['name' => $raw]);
    }

    // -----------------------------------------------------
    // Resolve Teams
    // -----------------------------------------------------

    /** @return Team[] */
    private function resolveTeams(string $csv): array
    {
        $teams = [];
        $parts = array_filter(array_map('trim', explode(',', $csv)));

        foreach ($parts as $part) {
            $team = null;

            if (ctype_digit($part)) {
                $team = $this->teamRepository->find((int) $part);
            }

            if (!$team) {
                $team = $this->teamRepository->findOneBy(['name' => $part]);
            }

            if ($team && !in_array($team, $teams, true)) {
                $teams[] = $team;
            }
        }

        return $teams;
    }

    // -----------------------------------------------------
    // Preview Helpers
    // -----------------------------------------------------

    private function getClientPreview(): array
    {
        $clients = method_exists($this->clientRepository, 'findRecentForTerminal')
            ? $this->clientRepository->findRecentForTerminal(5)
            : $this->clientRepository->findBy([], ['id' => 'DESC'], 5);

        return array_map(
            static fn (Client $client, int $index) => [
                'index' => $index,
                'id' => $client->getId(),
                'name' => $client->getName(),
            ],
            $clients,
            array_keys($clients),
        );
    }

    private function getTeamPreview(): array
    {
        $teams = method_exists($this->teamRepository, 'findRecentForTerminal')
            ? $this->teamRepository->findRecentForTerminal(5)
            : $this->teamRepository->findBy([], ['id' => 'DESC'], 5);

        return array_map(
            static fn (Team $team, int $index) => [
                'index' => $index,
                'id' => $team->getId(),
                'name' => $team->getName(),
            ],
            $teams,
            array_keys($teams),
        );
    }

    private function getRatePreview(): array
    {
        $rates = method_exists($this->rateRepository, 'findPreviewForTerminal')
            ? $this->rateRepository->findPreviewForTerminal(5)
            : $this->rateRepository->findBy([], ['id' => 'DESC'], 5);

        return array_map(
            static fn (Rate $rate, int $index) => [
                'index' => $index,
                'id' => $rate->getId(),
                'name' => $rate->getName(),
                'value' => $rate->getValue(),
            ],
            $rates,
            array_keys($rates),
        );
    }

    // -----------------------------------------------------
    // Parse Priority
    // -----------------------------------------------------

    private function parsePriority(?string $raw): ?Priority
    {
        if ($raw === null) {
            return null;
        }

        $raw = mb_strtolower(trim($raw));

        if ($raw === '') {
            return null;
        }

        $numeric = [
            '1' => Priority::LOW,
            '2' => Priority::MEDIUM,
            '3' => Priority::HIGH,
            '4' => Priority::CRITICAL,
        ];

        if (isset($numeric[$raw])) {
            return $numeric[$raw];
        }

        foreach (Priority::cases() as $case) {
            if (mb_strtolower($case->name) === mb_strtoupper($raw)) {
                return $case;
            }

            if (mb_strtolower((string) $case->value) === $raw) {
                return $case;
            }
        }

        return null;
    }

    // -----------------------------------------------------
    // Parse Estimated Time
    // -----------------------------------------------------

    private function parseEstimatedTime(string $raw): ?int
    {
        $raw = mb_strtolower(trim($raw));

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $hours = 0;
        $minutes = 0;

        if (preg_match('#(\d+)\s*h#', $raw, $match)) {
            $hours = (int) $match[1];
        }

        if (preg_match('#(\d+)\s*m#', $raw, $match)) {
            $minutes = (int) $match[1];
        }

        if ($hours === 0 && $minutes === 0) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function parseEstimatedTimeOrFail(string $raw): int
    {
        $minutes = $this->parseEstimatedTime($raw);

        if ($minutes === null) {
            throw new \InvalidArgumentException('Invalid estimated time.');
        }

        return $minutes;
    }

    // -----------------------------------------------------
    // Parse Budget
    // -----------------------------------------------------

    private function parseBudget(string $raw): string
    {
        return str_replace(',', '.', trim($raw));
    }

    // -----------------------------------------------------
    // Parse Bool
    // -----------------------------------------------------

    private function parseBool(string $raw): bool
    {
        return in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    // -----------------------------------------------------
    // Format Minutes
    // -----------------------------------------------------

    private function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes <= 0) {
            return '0h 0m';
        }

        return sprintf('%dh %dm', intdiv($minutes, 60), $minutes % 60);
    }

    // -----------------------------------------------------
    // Nullable String Helper
    // -----------------------------------------------------

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

// -----------------------------------------------------
// TODO — Project Terminal Module
// -----------------------------------------------------
//
// Files to update later:
//
// 1. src/Service/Terminal/Admin/TerminalProjectService.php
//    - Add deadline warning in confirm step when deadline is in the past.
//    - Improve confirm fields/layout if needed.
//    - Add richer command payloads for clickable UI actions later.
//
// 2. templates/terminals/admin/terminal_commands/projects.html.twig
//    - Improve visual layout for project show.
//    - Improve Teams rendering so it does not create awkward line breaks.
//    - Make Client, Teams, Todos, and Rate visually ready for clickable actions.
//
// 3. templates/terminals/admin/terminal_commands/clients.html.twig
//    - Make project rows under client show clickable later.
//    - Clicking project should run: p.s <projectId>
//
// 4. templates/terminals/admin/terminal_commands/partials/_resource_show.html.twig
//    - Improve generic confirm/show layout.
//    - Support warning rows better.
//    - Support future action metadata.
//
// 5. assets/controllers/terminal_controller.js
//    - Later: support clickable terminal actions.
//    - Example: click project row => send command "p.s 8".
//    - Example: click todos count => send command "todo:list --project=8".
//
// Future terminal commands:
//
// - p.s <id>
// - p.l --client=<id>
// - c.s <id>
// - team.s <id>
// - todo:list --project=<id>
//
// Future clickable UI actions:
//
// - Client in project show => c.s <clientId>
// - Team in project show => team.s <teamId>
// - Todos count in project show => todo:list --project=<projectId>
// - Project under client show => p.s <projectId>
//
// Notes:
//
// - Plain estimated-time numbers are currently interpreted as minutes.
// - Users should use "h" for hours, e.g. "140h" or "2h30m".
// - Confirm step should warn but not block if deadline is in the past.
//
}
