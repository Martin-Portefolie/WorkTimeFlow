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
use App\Service\Terminal\ArgsParser;
use App\Service\Terminal\DateInputParser;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ProjectTerminalService
{
    private const WZ_PROJECT_ADD = 'projects:add';

    private const ADD_STEPS = [
        [
            'key'     => 'client',
            'label'   => 'Client (id or name, required)',
            'example' => '1 or Viden Djurs',
        ],
        [
            'key'     => 'teams',
            'label'   => 'Teams (ids or names, comma separated, Enter to skip)',
            'example' => 'Eksammens Team, Another Team',
        ],
        [
            'key'     => 'name',
            'label'   => 'Project name?',
            'example' => 'Hest Test Calendar - Multimedie Integrator Svendeprøve',
        ],
        [
            'key'     => 'description',
            'label'   => 'Short description (Enter to skip)',
            'example' => 'Et tidsregistrerings system.',
        ],
        [
            'key'     => 'priority',
            'label'   => 'Priority [ 1=low, 2=medium, 3=high, 4=critical ] (Enter for low)',
            'example' => '2',
        ],
        [
            'key'     => 'deadline',
            'label'   => 'Deadline (days: 30, date: 08/03/2025, or weekday: mon-2, next fri — Enter for +30 days)',
            'example' => '30 or mon-2 or 08/03/2025',
        ],
        [
            'key'     => 'estimated',
            'label'   => 'Estimated time (e.g. 140h, 2h30m, 90m, Enter to skip)',
            'example' => '140h',
        ],
        [
            'key'     => 'budget',
            'label'   => 'Estimated budget (decimal, Enter for 0)',
            'example' => '0.00',
        ],
        [
            'key'     => 'rate',
            'label'   => 'Rate (id, name, or number from list below, Enter to skip)',
            'example' => '0 or Eksammens Rate',
        ],
    ];

    public function __construct(
        private ProjectRepository      $projects,
        private ClientRepository       $clients,
        private RateRepository         $rates,
        private TeamRepository         $teams,
        private EntityManagerInterface $em,
        private TerminalStateService   $wiz,
        private Security               $security,
        private DateInputParser         $dateInputParser,
    ) {}

    // =====================================================================
    // LIST
    // =====================================================================

    public function listCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        // Optional search: id or name/client-name fragment
        $search = isset($args[0]) ? trim($args[0]) : null;
        if ($search === '') {
            $search = null;
        }

        // Archived filter
        $archivedFlag = strtolower((string) ($flags['archived'] ?? 'active'));
        if (!in_array($archivedFlag, ['active', 'archived', 'all'], true)) {
            $archivedFlag = 'active';
        }

        $limit = 50;

        $qb = $this->projects->createQueryBuilder('p')
            ->leftJoin('p.client', 'c')->addSelect('c')
            ->leftJoin('p.teams', 't')->addSelect('t');

        // Archived filter
        if ($archivedFlag === 'active') {
            $qb->andWhere('p.isArchived = :arch')->setParameter('arch', false);
        } elseif ($archivedFlag === 'archived') {
            $qb->andWhere('p.isArchived = :arch')->setParameter('arch', true);
        }

        // Search filter (id or name/client-name)
        if ($search !== null) {
            if (ctype_digit($search)) {
                // Try ID OR fallback to name containing digit string
                $qb
                    ->andWhere('p.id = :pid OR LOWER(p.name) LIKE :pname OR LOWER(c.name) LIKE :cname')
                    ->setParameter('pid', (int) $search)
                    ->setParameter('pname', '%' . mb_strtolower($search) . '%')
                    ->setParameter('cname', '%' . mb_strtolower($search) . '%');
            } else {
                $qb
                    ->andWhere('LOWER(p.name) LIKE :pname OR LOWER(c.name) LIKE :cname')
                    ->setParameter('pname', '%' . mb_strtolower($search) . '%')
                    ->setParameter('cname', '%' . mb_strtolower($search) . '%');
            }
        }

        // We will sort in PHP to handle "overrun first"
        $qb->orderBy('p.lastUpdated', 'DESC');
        $projects = $qb->getQuery()->getResult();

        $now  = new \DateTimeImmutable('now');
        $rows = [];

        /** @var Project $p */
        foreach ($projects as $p) {
            $totalMinutes    = $p->getTotalMinutesUsed();
            $estimated       = $p->getEstimatedMinutes();
            $remaining       = null;
            $isOverrun       = false;

            if ($estimated !== null) {
                $remaining = $estimated - $totalMinutes;
                if ($remaining < 0) {
                    $isOverrun = true;
                }
            }

            $deadline        = $p->getDeadline();
            $deadlineExpired = $deadline !== null && $deadline < $now;

            $rows[] = [
                'id'                   => $p->getId(),
                'name'                 => (string) $p->getName(),
                'clientName'           => $p->getClient()?->getName() ?? '—',
                'priority'             => $p->getPriority()?->name ?? 'MEDIUM',
                'deadline'             => $deadline,
                'deadlineFormatted'    => $deadline?->format('d/m/Y'),
                'deadlineExpired'      => $deadlineExpired,
                'estimatedMinutes'     => $estimated,
                'estimatedLabel'       => $this->formatMinutes($estimated),
                'totalMinutesUsed'     => $totalMinutes,
                'totalUsedLabel'       => $this->formatMinutes($totalMinutes),
                'remainingMinutes'     => $remaining,
                'remainingLabel'       => $remaining !== null ? $this->formatMinutes(max($remaining, 0)) : null,
                'isOverrun'            => $isOverrun,
                'teamsCount'           => $p->getTeams()->count(),
                'rateName'             => $p->getRate()?->getName() ?? null,
                'isArchived'           => $p->isArchived(),
                'isPaid'               => $p->isPaid(),
                'lastUpdated'          => $p->getLastUpdated(),
                'lastUpdatedFormatted' => $p->getLastUpdated()?->format('d/m/Y'),
            ];
        }

        // Overrun first, then newest
        usort($rows, static function (array $a, array $b): int {
            if ($a['isOverrun'] !== $b['isOverrun']) {
                return $a['isOverrun'] ? -1 : 1; // overrun first
            }

            $aTs = $a['lastUpdated'] instanceof \DateTimeInterface ? $a['lastUpdated']->getTimestamp() : 0;
            $bTs = $b['lastUpdated'] instanceof \DateTimeInterface ? $b['lastUpdated']->getTimestamp() : 0;

            return $bTs <=> $aTs; // newest first
        });

        $rows = array_slice($rows, 0, $limit);

        return [
            'view'    => 'terminal/admin/terminal_commands/_projects_list_admin.html.twig',
            'vars'    => [
                'projects'      => $rows,
                'search'        => $search,
                'archivedFlag'  => $archivedFlag,
                'shownCount'    => count($rows),
                'limit'         => $limit,
            ],
            'success' => true,
        ];
    }

    // =====================================================================
    // SHOW
    // =====================================================================

    public function showCommand(array $tokens): array
    {
        ['args' => $args] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return [
                'output'  => 'Usage: projects:show <id|name>',
                'success' => false,
            ];
        }

        $needle = trim($args[0]);

        /** @var Project|null $project */
        $project = null;

        if (ctype_digit($needle)) {
            $project = $this->projects->find((int) $needle);
        }
        if (!$project) {
            $project = $this->projects->findOneBy(['name' => $needle]);
        }

        if (!$project) {
            return [
                'output'  => sprintf('Project "%s" not found.', $needle),
                'success' => false,
            ];
        }

        $summary = $this->buildSummary($project);

        return [
            'view'    => 'terminal/admin/terminal_commands/_projects_show_admin.html.twig',
            'vars'    => [
                'project' => $project,
                'summary' => $summary,
            ],
            'success' => true,
        ];
    }

    private function buildSummary(Project $p): array
    {
        $now           = new \DateTimeImmutable('now');
        $deadline      = $p->getDeadline();
        $deadlineExp   = $deadline !== null && $deadline < $now;

        $totalMinutes  = $p->getTotalMinutesUsed();
        $estimated     = $p->getEstimatedMinutes();
        $remaining     = null;
        $isOverrun     = false;

        if ($estimated !== null) {
            $remaining = $estimated - $totalMinutes;
            if ($remaining < 0) {
                $isOverrun = true;
            }
        }

        $client        = $p->getClient();
        $rate          = $p->getRate();
        $teams         = $p->getTeams();
        $todos         = $p->getTodos();

        return [
            'deadlineFormatted'    => $deadline?->format('d/m/Y'),
            'deadlineExpired'      => $deadlineExp,
            'priority'             => $p->getPriority()?->name ?? 'MEDIUM',
            'estimatedMinutes'     => $estimated,
            'estimatedLabel'       => $this->formatMinutes($estimated),
            'totalMinutesUsed'     => $totalMinutes,
            'totalUsedLabel'       => $this->formatMinutes($totalMinutes),
            'remainingMinutes'     => $remaining,
            'remainingLabel'       => $remaining !== null ? $this->formatMinutes(max($remaining, 0)) : null,
            'isOverrun'            => $isOverrun,
            'clientName'           => $client?->getName() ?? '—',
            'clientId'             => $client?->getId(),
            'rateName'             => $rate?->getName() ?? null,
            'rateValue'            => $rate?->getValue(),
            'teamsCount'           => $teams->count(),
            'todosCount'           => $todos->count(),
            'isArchived'           => $p->isArchived(),
            'isPaid'               => $p->isPaid() ?? false,
            'lastUpdated'          => $p->getLastUpdated(),
            'lastUpdatedFormatted' => $p->getLastUpdated()?->format('d/m/Y H:i'),
        ];
    }

    // =====================================================================
    // ADD (flags + wizard)
    // =====================================================================

    public function addCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        // Non-interactive: flags present
        if (!empty($flags)) {
            $data = [
                'name'        => $flags['name']        ?? null,
                'description' => $flags['description'] ?? null,
                'client'      => $flags['client']      ?? null,
                'priority'    => $flags['priority']    ?? null,
                'deadline'    => $flags['deadline']    ?? null,
                'estimated'   => $flags['estimated']   ?? $flags['estimated-minutes'] ?? null,
                'budget'      => $flags['budget']      ?? $flags['estimated-budget'] ?? null,
                'rate'        => $flags['rate']        ?? null,
                'teams'       => $flags['teams']       ?? null,
            ];

            $result = $this->createProjectFromData($data);
            if (!$result['success']) {
                return [
                    'output'  => $result['error'],
                    'success' => false,
                ];
            }

            /** @var Project $project */
            $project = $result['project'];

            return [
                'view'    => 'terminal/admin/terminal_commands/_project_created_summary_admin.html.twig',
                'vars'    => [
                    'project'   => $project,
                    'client'    => $project->getClient(),
                    'rate'      => $project->getRate(),
                    'teams'     => $project->getTeams(),
                ],
                'success' => true,
            ];
        }

        // Wizard start
        $userKey = $this->currentUserKey();
        $this->wiz->set($userKey, [
            'mode' => self::WZ_PROJECT_ADD,
            'step' => 0,
            'data' => [],
        ]);

        $step  = self::ADD_STEPS[0];
        $total = count(self::ADD_STEPS);

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => [
                'label'   => $step['label'],
                'example' => $step['example'] ?? null,
                'step'    => 1,
                'total'   => $total,
            ],
            'success' => true,
            'await'   => true,
        ];
    }

    // =====================================================================
    // UPDATE
    // =====================================================================

    /**
     * projects:update <id>
     *  [--name=] [--description=] [--client=<id|name>] [--priority=low|medium|high]
     *  [--deadline=...] [--estimated-minutes=] [--estimated=...] [--estimated-budget=] [--budget=]
     *  [--rate=<id|name>] [--is-archived=0|1] [--is-paid=0|1]
     */
    public function updateCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: projects:update <id> [--flags...]', 'success' => false];
        }

        $id = (int) $args[0];
        if ($id <= 0) {
            return ['output' => 'Invalid project id.', 'success' => false];
        }

        /** @var Project|null $project */
        $project = $this->projects->find($id);
        if (!$project) {
            return ['output' => 'Project not found.', 'success' => false];
        }

        $changed = [];

        if (isset($flags['name'])) {
            $project->setName($flags['name']);
            $changed[] = 'name';
        }

        if (array_key_exists('description', $flags)) {
            $project->setDescription($flags['description'] !== '' ? $flags['description'] : null);
            $changed[] = 'description';
        }

        if (isset($flags['client'])) {
            $client = $this->resolveClient($flags['client']);
            if (!$client) {
                return ['output' => 'Client not found.', 'success' => false];
            }
            $project->setClient($client);
            $changed[] = 'client';
        }

        if (isset($flags['priority'])) {
            $priority = $this->parsePriority($flags['priority']);
            if (!$priority) {
                return ['output' => 'Invalid priority. Use 1=low, 2=medium, 3=high, 4=critical or low|medium|high|critical.', 'success' => false];
            }
            $project->setPriority($priority);
            $changed[] = 'priority';
        }

        if (isset($flags['deadline'])) {
            $raw = (string) $flags['deadline'];

            $rawTrimmed = trim($raw);

            if ($rawTrimmed === '') {
                // Empty: treat as "no change" to avoid violating NOT NULL
                // (if you wanted: you could decide empty = reset to +30 days here)
            } else {
                $parsed = $this->dateParser->parseFlexibleDate($rawTrimmed, null, null);

                if (!$parsed) {
                    return ['output' => 'Invalid deadline format.', 'success' => false];
                }

                $project->setDeadline($parsed);
                $changed[] = 'deadline';
            }
        }

        $estimatedRaw = $flags['estimated-minutes'] ?? $flags['estimated'] ?? null;
        if ($estimatedRaw !== null) {
            if ($estimatedRaw === '') {
                $project->setEstimatedTime(null);
            } else {
                $minutes = ctype_digit((string) $estimatedRaw)
                    ? (int) $estimatedRaw
                    : $this->parseEstimatedTime($estimatedRaw);

                if ($minutes === null) {
                    return ['output' => 'Invalid estimated time.', 'success' => false];
                }
                $project->setEstimatedTime($minutes);
            }
            $changed[] = 'estimatedMinutes';
        }

        $budgetRaw = $flags['estimated-budget'] ?? $flags['budget'] ?? null;
        if ($budgetRaw !== null) {
            if ($budgetRaw === '') {
                $project->setEstimatedBudget(null);
            } else {
                $project->setEstimatedBudget($this->parseBudget($budgetRaw));
            }
            $changed[] = 'estimatedBudget';
        }

        if (isset($flags['rate'])) {
            if ($flags['rate'] === '') {
                $project->setRate(null);
            } else {
                $rate = $this->resolveRate($flags['rate']);
                if (!$rate) {
                    return ['output' => 'Rate not found.', 'success' => false];
                }
                $project->setRate($rate);
            }
            $changed[] = 'rate';
        }

        if (isset($flags['is-archived'])) {
            $project->setArchived($this->parseBool($flags['is-archived']));
            $changed[] = 'isArchived';
        }

        if (isset($flags['is-paid'])) {
            $project->setIsPaid($this->parseBool($flags['is-paid']));
            $changed[] = 'isPaid';
        }

        if (!$changed) {
            return ['output' => 'Nothing to update.', 'success' => false];
        }

        $this->em->flush();

        return [
            'output'  => 'Updated: ' . implode(', ', $changed),
            'success' => true,
        ];
    }

    // =====================================================================
    // DELETE
    // =====================================================================

    /**
     * projects:delete <id> [--force]
     */
    public function deleteCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: projects:delete <id> [--force]', 'success' => false];
        }

        $id = (int) $args[0];
        if ($id <= 0) {
            return ['output' => 'Invalid project id.', 'success' => false];
        }

        /** @var Project|null $project */
        $project = $this->projects->find($id);
        if (!$project) {
            return ['output' => 'Project not found.', 'success' => false];
        }

        if (!isset($flags['force'])) {
            return [
                'output'  => 'Add --force to actually delete this project.',
                'success' => false,
            ];
        }

        $name = $project->getName();

        $this->em->remove($project);
        $this->em->flush();

        return [
            'output'  => sprintf('Project id=%d ("%s") deleted.', $id, (string) $name),
            'success' => true,
        ];
    }

    // =====================================================================
    // WIZARD HANDLING
    // =====================================================================

    public function handleInteractive(string $rawInput): ?array
    {
        $userKey = $this->currentUserKey();
        $state   = $this->wiz->get($userKey);
        if (!$state) {
            return null;
        }

        $trimmed = trim($rawInput);

        // Restart wizard
        if ($trimmed === 'projects:add') {
            $this->wiz->set($userKey, [
                'mode' => self::WZ_PROJECT_ADD,
                'step' => 0,
                'data' => [],
            ]);

            $step  = self::ADD_STEPS[0];
            $total = count(self::ADD_STEPS);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $step['label'],
                    'example' => $step['example'] ?? null,
                    'step'    => 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // Cancel
        if ($trimmed === 'cancel') {
            $this->wiz->clear($userKey);
            return ['output' => 'Canceled.', 'success' => true];
        }

        return match ($state['mode'] ?? null) {
            self::WZ_PROJECT_ADD => $this->handleAddStep($state, $rawInput, $userKey),
            default => null,
        };
    }

    private function handleAddStep(array $state, string $rawInput, string $userKey): array
    {
        $steps = self::ADD_STEPS;
        $i     = (int) ($state['step'] ?? 0);

        if ($i >= count($steps)) {
            // Safety: if somehow out of range, just finalize
            $result = $this->createProjectFromData($state['data'] ?? []);
            $this->wiz->clear($userKey);

            if (!$result['success']) {
                return ['output' => $result['error'], 'success' => false];
            }

            /** @var Project $project */
            $project = $result['project'];

            return [
                'view'    => 'terminal/admin/terminal_commands/_project_created_summary_admin.html.twig',
                'vars'    => [
                    'project' => $project,
                    'client'  => $project->getClient(),
                    'rate'    => $project->getRate(),
                    'teams'   => $project->getTeams(),
                ],
                'success' => true,
            ];
        }

        $step   = $steps[$i];
        $key    = $step['key'];
        $val    = trim($rawInput);
        $data   = $state['data'] ?? [];
        $error  = null;

        switch ($key) {
            case 'client':
                if ($val === '') {
                    $error = 'Client is required. Use an id, name, or one of the numbers [0..n] shown.';
                    break;
                }
                $client = $this->resolveClientFromWizard($val);
                if (!$client) {
                    $error = 'Client not found. Try a valid id, exact name, or a number from the list.';
                    break;
                }
                // store a stable identifier; easiest is client id
                $data['client'] = (string) $client->getId();
                break;


            case 'teams':
                if ($val === '') {
                    $data['teams'] = '';
                    break;
                }
                $resolved = $this->resolveTeamsFromWizard($val);
                if (!$resolved) {
                    $error = 'No teams matched that input. Use ids, names, or numbers from the list.';
                    break;
                }
                // store raw input; createProjectFromData will resolve again
                $data['teams'] = $val;
                break;

            case 'name':
                if ($val === '') {
                    $error = 'Project name is required.';
                    break;
                }
                $data['name'] = $val;
                break;

            case 'description':
                // optional
                $data['description'] = $val;
                break;

            case 'priority':
                if ($val === '') {
                    // default to Low
                    $data['priority'] = 'low';
                } else {
                    if (!$this->parsePriority($val)) {
                        $error = 'Invalid priority. Use 1=low, 2=medium, 3=high or low|medium|high.';
                        break;
                    }
                    $data['priority'] = $val;
                }
                break;

            case 'deadline':
                if ($val === '') {
                    // store empty; createProjectFromData will treat empty as +30 days
                    $data['deadline'] = '';
                } else {
                    $parsed = $this->dateInputParser->parseFlexibleDate($val, null, 30);
                    if (!$parsed) {
                        $error = 'Invalid deadline. Use days (10), date (08/03/2025) or weekday (mon-2, next fri).';
                        break;
                    }
                    // we keep the raw value; parsing is just for validation here
                    $data['deadline'] = $val;
                }
                break;

            case 'estimated':
                if ($val === '') {
                    $data['estimated'] = '';
                } else {
                    if ($this->parseEstimatedTime($val) === null) {
                        $error = 'Invalid estimated time. Use 140h, 2h30m, 90m or plain minutes.';
                        break;
                    }
                    $data['estimated'] = $val;
                }
                break;

            case 'budget':
                if ($val === '') {
                    // default to 0 for wizard
                    $data['budget'] = '0';
                } else {
                    $data['budget'] = $val;
                }
                break;

            case 'rate':
                if ($val === '') {
                    $data['rate'] = '';
                } else {
                    $rate = $this->resolveRateFromWizard($val);
                    if (!$rate) {
                        $error = 'Rate not found. Type a rate name, id, or one of the numbers [0..n] shown above.';
                        break;
                    }
                    // store id (stable) for createProjectFromData
                    $data['rate'] = (string) $rate->getId();
                }
                break;

            default:
                // Fallback: just store raw
                $data[$key] = $val;
        }

        // If there was an error, DO NOT advance; re-render same step with error
        if ($error !== null) {
            $state['data'] = $data;
            $state['step'] = $i;
            $this->wiz->set($userKey, $state);

            return $this->buildStepView($i, $state, $error);
        }

        // No error -> advance step
        $i++;
        $state['data'] = $data;
        $state['step'] = $i;
        $this->wiz->set($userKey, $state);

        // More steps? Ask next question
        if ($i < count($steps)) {
            return $this->buildStepView($i, $state);
        }

        // Finalize -> create project
        $result = $this->createProjectFromData($state['data'] ?? []);
        $this->wiz->clear($userKey);

        if (!$result['success']) {
            // This should now be rare, because we validated per-step
            return ['output' => $result['error'], 'success' => false];
        }

        /** @var Project $project */
        $project = $result['project'];

        return [
            'view'    => 'terminal/admin/terminal_commands/_project_created_summary_admin.html.twig',
            'vars'    => [
                'project' => $project,
                'client'  => $project->getClient(),
                'rate'    => $project->getRate(),
                'teams'   => $project->getTeams(),
            ],
            'success' => true,
        ];
    }


    // =====================================================================
    // CREATE FROM DATA (shared by flags + wizard)
    // =====================================================================

    /** @return array{success:bool, project?:Project, error?:string} */
    private function createProjectFromData(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'error' => 'Project name is required.'];
        }

        $clientKey = trim((string) ($data['client'] ?? ''));
        $client = $this->resolveClient($clientKey);
        if (!$client) {
            return ['success' => false, 'error' => 'Client is required and must exist.'];
        }

        $priorityRaw = trim((string) ($data['priority'] ?? ''));
        $priority = $priorityRaw === '' ? Priority::MEDIUM : $this->parsePriority($priorityRaw);
        if (!$priority) {
            return ['success' => false, 'error' => 'Invalid priority. Use low, medium, high.'];
        }

        $deadlineInput = $data['deadline'] ?? '';
        $deadline = $this->dateInputParser->parseFlexibleDate(
            $deadlineInput,
            null,   // base = today
            30      // default 30 days if empty (wizard)
        );

        if (!$deadline) {
            return ['success' => false, 'error' => 'Invalid deadline format.'];
        }

        $estimatedRaw = trim((string) ($data['estimated'] ?? ''));
        $estimatedMinutes = null;
        if ($estimatedRaw !== '') {
            $estimatedMinutes = $this->parseEstimatedTime($estimatedRaw);
            if ($estimatedMinutes === null) {
                return ['success' => false, 'error' => 'Invalid estimated time.'];
            }
        }

        $budgetRaw = trim((string) ($data['budget'] ?? ''));
        $budget = $budgetRaw !== '' ? $this->parseBudget($budgetRaw) : null;

        $rateRaw = trim((string) ($data['rate'] ?? ''));
        $rate = null;
        if ($rateRaw !== '') {
            $rate = $this->resolveRate($rateRaw);
            if (!$rate) {
                return ['success' => false, 'error' => 'Rate not found.'];
            }
        }

        $teamsRaw = trim((string) ($data['teams'] ?? ''));
        $teams = [];
        if ($teamsRaw !== '') {
            // wizard may contain numeric indices; use wizard-aware resolver
            $teams = $this->resolveTeamsFromWizard($teamsRaw);
        }

        $project = new Project();
        $project->setName($name);
        $project->setDescription(($data['description'] ?? '') !== '' ? $data['description'] : null);
        $project->setClient($client);
        $project->setPriority($priority);
        if ($deadline) {
            $project->setDeadline($deadline);
        }
        if ($estimatedMinutes !== null) {
            $project->setEstimatedTime($estimatedMinutes);
        }
        if ($budget !== null) {
            $project->setEstimatedBudget($budget);
        }
        if ($rate) {
            $project->setRate($rate);
        }

        foreach ($teams as $team) {
            $project->addTeam($team);
        }

        $this->em->persist($project);
        $this->em->flush();

        return ['success' => true, 'project' => $project];
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    private function resolveClientFromWizard(string $input): ?Client
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // numeric index into preview list
        if (ctype_digit($input)) {
            $idx    = (int) $input;
            $list   = $this->getClientPreview();
            if (isset($list[$idx])) {
                return $this->clients->find($list[$idx]['id']);
            }
        }

        // fallback: id or name
        return $this->resolveClient($input);
    }

    /**
     * Accepts:
     *  - comma-separated numeric indices (0,1,2)
     *  - comma-separated ids or names (existing behaviour)
     *
     * Returns array<Team>
     */
    private function resolveTeamsFromWizard(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $csv)));

        if (!$parts) {
            return [];
        }

        $preview = $this->getTeamPreview();
        $byIndex = [];
        foreach ($preview as $row) {
            $byIndex[(string) $row['index']] = $row['id']; // "0" => teamId
        }

        $result = [];

        foreach ($parts as $piece) {
            $team = null;

            // 1) numeric preview index?
            if (ctype_digit($piece) && isset($byIndex[$piece])) {
                $team = $this->teams->find($byIndex[$piece]);
            }

            // 2) numeric id?
            if (!$team && ctype_digit($piece)) {
                $team = $this->teams->find((int) $piece);
            }

            // 3) name?
            if (!$team) {
                $team = $this->teams->findOneBy(['name' => $piece]);
            }

            if ($team) {
                $result[] = $team;
            }
        }

        return $result;
    }


    private function resolveRateFromWizard(string $input): ?Rate
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // 0,1,2,... => index in preview list
        if (ctype_digit($input)) {
            $idx   = (int) $input;
            $list  = $this->getRatePreview();
            if (isset($list[$idx])) {
                // we only have id + name + value in preview; refetch full Rate by id
                return $this->rates->find($list[$idx]['id']);
            }
        }

        // Fallback: existing behaviour (id or name)
        return $this->resolveRate($input);
    }

    private function getClientPreview(): array
    {
        $clients = $this->clients->findRecentForTerminal(5);

        $out   = [];
        $index = 0;

        /** @var Client $c */
        foreach ($clients as $c) {
            $out[] = [
                'index' => $index++,                       // 0,1,2,...
                'id'    => $c->getId(),
                'name'  => $c->getName() ?? '(no name)',
            ];
        }

        return $out;
    }


    private function getTeamPreview(): array
    {
        $teams = $this->teams->findRecentForTerminal(5);

        $out   = [];
        $index = 0;

        /** @var Team $t */
        foreach ($teams as $t) {
            $out[] = [
                'index' => $index++,                       // 0,1,2,...
                'id'    => $t->getId(),
                'name'  => $t->getName() ?? '(no name)',
            ];
        }

        return $out;
    }

    private function getRatePreview(): array
    {
        $rates = $this->rates->findPreviewForTerminal(5);

        $out   = [];
        $index = 0;

        /** @var Rate $r */
        foreach ($rates as $r) {
            $out[] = [
                'index' => $index++,                 // 0,1,2,...
                'id'    => $r->getId(),
                'name'  => $r->getName(),
                'value' => $r->getValue(),
            ];
        }

        return $out;
    }

    private function buildStepView(int $stepIndex, ?array $state = null, ?string $error = null): array
    {
        $steps = self::ADD_STEPS;
        $step  = $steps[$stepIndex];
        $total = count($steps);

        $vars = [
            'label'   => $step['label'],
            'example' => $step['example'] ?? null,
            'step'    => $stepIndex + 1,
            'total'   => $total,
        ];

        if ($error !== null) {
            $vars['error'] = $error;
        }

        // Context-specific previews
        switch ($step['key']) {
            case 'client':
                $vars['clientsPreview'] = $this->getClientPreview();
                break;
            case 'teams':
                $vars['teamsPreview'] = $this->getTeamPreview();
                break;
            case 'rate':
                $vars['ratesPreview'] = $this->getRatePreview();
                break;
        }

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => $vars,
            'success' => true,
            'await'   => true,
        ];
    }

    private function currentUserKey(): string
    {
        $user = $this->security->getUser();
        if ($user && method_exists($user, 'getUserIdentifier')) {
            return (string) $user->getUserIdentifier();
        }
        return 'guest';
    }

    private function resolveClient(string $needle): ?Client
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            $client = $this->clients->find((int) $needle);
            if ($client) {
                return $client;
            }
        }

        return $this->clients->findOneBy(['name' => $needle]);
    }

    private function resolveRate(string $needle): ?Rate
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            $rate = $this->rates->find((int) $needle);
            if ($rate) {
                return $rate;
            }
        }

        return $this->rates->findOneBy(['name' => $needle]);
    }

    /** @return Team[] */
    private function resolveTeams(string $csv): array
    {
        $out = [];
        $parts = array_filter(array_map('trim', explode(',', $csv)));
        foreach ($parts as $piece) {
            if ($piece === '') {
                continue;
            }
            $team = null;
            if (ctype_digit($piece)) {
                $team = $this->teams->find((int) $piece);
            }
            if (!$team) {
                $team = $this->teams->findOneBy(['name' => $piece]);
            }
            if ($team) {
                $out[] = $team;
            }
        }
        return $out;
    }



    private function parsePriority(?string $raw): ?Priority
    {
        if ($raw === null) {
            return null;
        }

        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        // numeric shortcuts
        $mapNumeric = [
            '1' => Priority::LOW,
            '2' => Priority::MEDIUM,
            '3' => Priority::HIGH,
            '4' => Priority::CRITICAL,
        ];

        if (isset($mapNumeric[$raw])) {
            return $mapNumeric[$raw];
        }

        // string values (enum->value)
        foreach (Priority::cases() as $case) {
            if ($case->value === $raw) {
                return $case;
            }
        }

        return null;
    }



    /**
     * Accepts "140h", "2h30m", "90m", "75"
     */
    private function parseEstimatedTime(string $raw): ?int
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        // pure minutes int
        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $hours = 0;
        $mins  = 0;

        if (preg_match('#(\d+)\s*h#', $raw, $m)) {
            $hours = (int) $m[1];
        }
        if (preg_match('#(\d+)\s*m#', $raw, $m)) {
            $mins = (int) $m[1];
        }

        if ($hours === 0 && $mins === 0) {
            return null;
        }

        return $hours * 60 + $mins;
    }

    private function parseBudget(string $raw): string
    {
        $raw = trim($raw);
        // allow comma decimal, normalize to dot
        $raw = str_replace(',', '.', $raw);
        return $raw;
    }

    private function parseBool(string $raw): bool
    {
        $raw = strtolower(trim($raw));
        return in_array($raw, ['1','true','yes','y','on'], true);
    }

    /** @param int|null $minutes */
    private function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }
        if ($minutes <= 0) {
            return '0h 0m';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%dh %dm', $h, $m);
    }
}
