<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Team;
use App\Entity\User;
use App\Entity\Project;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use App\Service\Terminal\ArgsParser;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TeamTerminalService
{
    private const WZ_TEAMS_ADD = 'teams:add';

    private const TEAMS_ADD_STEPS = [
        [
            'key'     => 'name',
            'label'   => 'Team name?',
            'example' => 'Eksammens Team',
        ],
        [
            'key'     => 'project',
            'label'   => 'Project name (optional, Enter to skip)',
            'example' => 'Hest Test Calendar - Multimedie Integrator Svendeprøve',
        ],
        [
            'key'     => 'user_emails',
            'label'   => 'User emails (comma-separated, optional, Enter to skip)',
            'example' => 'm@m.com, other@example.com',
        ],
    ];

    public function __construct(
        private TeamRepository          $teams,
        private UserRepository          $users,
        private ProjectRepository       $projects,
        private EntityManagerInterface  $em,
        private TerminalStateService    $wiz,
        private Security                $security,
    ) {}

    // ---------------------------------------------------------------------
    // Public command API
    // ---------------------------------------------------------------------

    /**
     * teams:list
     *
     * Lists all teams grouped/sorted by project.
     */
    public function listCommand(array $tokens): array
    {
        $allTeams = $this->teams->findAll();

        // Group by project name (or "No project")
        $groups = [];

        /** @var Team $team */
        foreach ($allTeams as $team) {
            $projects = $team->getProjects();
            if ($projects->isEmpty()) {
                $key   = 'No project';
                $label = 'No project';
                $groups[$key]['projectName'] = $label;
                $groups[$key]['teams'] ??= [];
                $groups[$key]['teams'][] = $this->normalizeTeamRow($team);
            } else {
                foreach ($projects as $project) {
                    /** @var Project $project */
                    $key   = $project->getName() ?? 'Unknown project';
                    $label = $key;
                    $groups[$key]['projectName'] = $label;
                    $groups[$key]['teams'] ??= [];
                    $groups[$key]['teams'][] = $this->normalizeTeamRow($team);
                }
            }
        }

        // Sort groups by projectName
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        // Sort teams inside each group by team name
        foreach ($groups as &$group) {
            usort($group['teams'], static function (array $a, array $b): int {
                return strcasecmp($a['name'], $b['name']);
            });
        }
        unset($group);

        return [
            'view'    => 'terminal/admin/terminal_commands/_teams_list_admin.html.twig',
            'vars'    => [
                'groups' => array_values($groups),
            ],
            'success' => true,
        ];
    }

    /**
     * teams:show <id|name>
     */
    public function showCommand(array $tokens): array
    {
        ['args' => $args] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: teams:show <id|name>', 'success' => false];
        }

        $needle = trim($args[0]);

        $team = null;
        if (ctype_digit($needle)) {
            $team = $this->teams->find((int) $needle);
        }
        if (!$team) {
            $team = $this->teams->findOneBy(['name' => $needle]);
        }

        if (!$team) {
            return ['output' => sprintf('Team "%s" not found.', $needle), 'success' => false];
        }

        return [
            'view'    => 'terminal/admin/terminal_commands/_teams_show_admin.html.twig',
            'vars'    => [
                'team'     => $team,
                'users'    => $team->getUsers(),
                'projects' => $team->getProjects(),
            ],
            'success' => true,
        ];
    }

    /**
     * teams:add
     *
     * - With flags:
     *   teams:add --name="Eksammens Team" --project="Project Name" --users="m@m.com,foo@bar.com"
     *
     * - Without flags: interactive wizard.
     */
    public function addCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!empty($flags)) {
            return $this->addNonInteractive($flags);
        }

        // Wizard start
        $userKey = $this->currentUserKey();
        $this->wiz->set($userKey, [
            'mode' => self::WZ_TEAMS_ADD,
            'step' => 0,
            'data' => [],
        ]);

        $step  = self::TEAMS_ADD_STEPS[0];
        $total = count(self::TEAMS_ADD_STEPS);

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

    /**
     * teams:update <id> [--name=<new_name>]
     */
    public function updateCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: teams:update <id> [--name=<new_name>]', 'success' => false];
        }

        $id = (int) $args[0];
        if ($id <= 0) {
            return ['output' => 'Invalid team id.', 'success' => false];
        }

        /** @var Team|null $team */
        $team = $this->teams->find($id);
        if (!$team) {
            return ['output' => 'Team not found.', 'success' => false];
        }

        $changed = [];

        if (array_key_exists('name', $flags)) {
            $team->setName((string) $flags['name']);
            $changed[] = 'name';
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

    /**
     * teams:delete <id>
     */
    public function deleteCommand(array $tokens): array
    {
        ['args' => $args] = ArgsParser::parse($tokens);

        if (!isset($args[0])) {
            return ['output' => 'Usage: teams:delete <id>', 'success' => false];
        }

        $id = (int) $args[0];
        if ($id <= 0) {
            return ['output' => 'Invalid team id.', 'success' => false];
        }

        /** @var Team|null $team */
        $team = $this->teams->find($id);
        if (!$team) {
            return ['output' => 'Team not found.', 'success' => false];
        }

        $name = $team->getName();

        $this->em->remove($team);
        $this->em->flush();

        return [
            'output'  => sprintf('Team id=%d ("%s") deleted.', $id, (string) $name),
            'success' => true,
        ];
    }

    /**
     * teams:add-user <teamId> <userId|email>
     */
    public function addUserCommand(array $tokens): array
    {
        ['args' => $args] = ArgsParser::parse($tokens);

        if (!isset($args[0], $args[1])) {
            return ['output' => 'Usage: teams:add-user <teamId> <userId|email>', 'success' => false];
        }

        $teamId  = (int) $args[0];
        $userKey = trim($args[1]);

        /** @var Team|null $team */
        $team = $this->teams->find($teamId);
        if (!$team) {
            return ['output' => 'Team not found.', 'success' => false];
        }

        $user = $this->findUserByIdOrEmail($userKey);
        if (!$user) {
            return ['output' => sprintf('User "%s" not found.', $userKey), 'success' => false];
        }

        $team->addUser($user);
        $this->em->flush();

        return [
            'output'  => sprintf('User %s added to team "%s".', $this->formatUserLabel($user), $team->getName()),
            'success' => true,
        ];
    }

    /**
     * teams:remove-user <teamId> <userId|email>
     */
    public function removeUserCommand(array $tokens): array
    {
        ['args' => $args] = ArgsParser::parse($tokens);

        if (!isset($args[0], $args[1])) {
            return ['output' => 'Usage: teams:remove-user <teamId> <userId|email>', 'success' => false];
        }

        $teamId  = (int) $args[0];
        $userKey = trim($args[1]);

        /** @var Team|null $team */
        $team = $this->teams->find($teamId);
        if (!$team) {
            return ['output' => 'Team not found.', 'success' => false];
        }

        $user = $this->findUserByIdOrEmail($userKey);
        if (!$user) {
            return ['output' => sprintf('User "%s" not found.', $userKey), 'success' => false];
        }

        $team->removeUser($user);
        $this->em->flush();

        return [
            'output'  => sprintf('User %s removed from team "%s".', $this->formatUserLabel($user), $team->getName()),
            'success' => true,
        ];
    }

    // ---------------------------------------------------------------------
    // Wizard integration
    // ---------------------------------------------------------------------

    /**
     * Called from TerminalService before routing commands.
     */
    public function handleInteractive(string $rawInput): ?array
    {
        $userKey = $this->currentUserKey();
        $state   = $this->wiz->get($userKey);
        if (!$state) {
            return null;
        }

        $trimmed = trim($rawInput);

        // restart
        if ($trimmed === 'teams:add') {
            $this->wiz->set($userKey, [
                'mode' => self::WZ_TEAMS_ADD,
                'step' => 0,
                'data' => [],
            ]);

            $step  = self::TEAMS_ADD_STEPS[0];
            $total = count(self::TEAMS_ADD_STEPS);

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

        // cancel
        if ($trimmed === 'cancel') {
            $this->wiz->clear($userKey);
            return ['output' => 'Canceled.', 'success' => true];
        }

        return match ($state['mode'] ?? null) {
            self::WZ_TEAMS_ADD => $this->handleTeamsAddStep($state, $rawInput, $userKey),
            default             => null,
        };
    }

    private function handleTeamsAddStep(array $state, string $rawInput, string $userKey): array
    {
        $i     = (int) ($state['step'] ?? 0);
        $steps = self::TEAMS_ADD_STEPS;

        if ($i < count($steps)) {
            $key = $steps[$i]['key'];
            $val = trim($rawInput);
            $state['data'][$key] = $val;
            $i++;
        }

        if ($i < count($steps)) {
            $state['step'] = $i;
            $this->wiz->set($userKey, $state);

            $step  = $steps[$i];
            $total = count($steps);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $step['label'],
                    'example' => $step['example'] ?? null,
                    'step'    => $i + 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // finalize: create team
        $data    = $state['data'] ?? [];
        $name    = trim($data['name'] ?? '');
        $project = trim($data['project'] ?? '');
        $emails  = trim($data['user_emails'] ?? '');

        if ($name === '') {
            $this->wiz->clear($userKey);
            return ['output' => 'Team name is required.', 'success' => false];
        }

        $team = new Team();
        $team->setName($name);

        // project (optional)
        $projectEntity = null;
        if ($project !== '') {
            $projectEntity = $this->projects->findOneBy(['name' => $project]);
            if ($projectEntity) {
                $team->addProject($projectEntity);
            }
        }

        // users (optional)
        $attachedUsers = [];
        if ($emails !== '') {
            $parts = array_filter(array_map('trim', explode(',', $emails)));
            foreach ($parts as $email) {
                if ($email === '') {
                    continue;
                }
                $user = $this->users->findOneBy(['email' => $email]);
                if ($user) {
                    $team->addUser($user);
                    $attachedUsers[] = $user;
                }
            }
        }

        $this->em->persist($team);
        $this->em->flush();

        $this->wiz->clear($userKey);

        return [
            'view'    => 'terminal/admin/terminal_commands/_team_created_summary_admin.html.twig',
            'vars'    => [
                'team'          => $team,
                'project'       => $projectEntity,
                'users'         => $attachedUsers,
            ],
            'success' => true,
        ];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function normalizeTeamRow(Team $team): array
    {
        $userCount = $team->getUsers()->count();
        $projectNames = [];
        foreach ($team->getProjects() as $p) {
            /** @var Project $p */
            $projectNames[] = $p->getName() ?? '—';
        }

        return [
            'id'           => (string) $team->getId(),
            'name'         => (string) $team->getName(),
            'userCount'    => $userCount,
            'projectNames' => $projectNames,
        ];
    }

    private function currentUserKey(): string
    {
        $user = $this->security->getUser();
        return method_exists($user, 'getUserIdentifier')
            ? (string) $user->getUserIdentifier()
            : 'guest';
    }

    private function findUserByIdOrEmail(string $key): ?User
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if (ctype_digit($key)) {
            $user = $this->users->find((int) $key);
            if ($user) {
                return $user;
            }
        }

        $user = $this->users->findOneBy(['email' => $key]);
        if ($user) {
            return $user;
        }

        return null;
    }

    private function formatUserLabel(User $user): string
    {
        $id    = $user->getId();
        $email = method_exists($user, 'getEmail') ? $user->getEmail() : null;
        $name  = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;

        return sprintf('#%d %s <%s>', $id, $name ?? '', $email ?? '');
    }
}
