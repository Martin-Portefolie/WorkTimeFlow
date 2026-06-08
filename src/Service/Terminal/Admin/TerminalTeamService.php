<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalTeamService
{
    private const VIEW = 'terminals/admin/terminal_commands/teams.html.twig';
    private const WIZARD_MODE = 'teams:add';

    private const ADD_STEPS = [
        [
            'key' => 'name',
            'label' => 'Team name',
            'example' => 'Design Team',
        ],
        [
            'key' => 'users',
            'label' => 'Users, comma separated. Leave empty to skip',
            'example' => '1, admin@example.com',
        ],
    ];

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly UserRepository $userRepository,
        private readonly TerminalStateService $state,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // -----------------------------------------------------
    // List Teams
    // -----------------------------------------------------

    public function list(array $flags = []): array
    {
        $q = isset($flags['q']) ? trim((string) $flags['q']) : null;
        $limit = isset($flags['limit']) ? max(1, (int) $flags['limit']) : 50;

        $qb = $this->teamRepository->createQueryBuilder('t')
            ->leftJoin('t.users', 'u')->addSelect('u')
            ->leftJoin('t.projects', 'p')->addSelect('p')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit);

        if ($q) {
            if (ctype_digit($q)) {
                $qb->andWhere('t.id = :id OR LOWER(t.name) LIKE :q OR LOWER(p.name) LIKE :q')
                    ->setParameter('id', (int) $q)
                    ->setParameter('q', '%' . mb_strtolower($q) . '%');
            } else {
                $qb->andWhere('LOWER(t.name) LIKE :q OR LOWER(p.name) LIKE :q')
                    ->setParameter('q', '%' . mb_strtolower($q) . '%');
            }
        }

        $rows = array_map(
            fn (Team $team): array => $this->teamListRow($team),
            $qb->getQuery()->getResult(),
        );

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'list',
                'resource' => 'teams',
                'title' => 'Teams',
                'description' => 'Teams available to admin commands.',
                'columns' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'name', 'label' => 'Team'],
                    ['key' => 'usersCount', 'label' => 'Users'],
                    ['key' => 'projectsCount', 'label' => 'Projects'],
                    ['key' => 'projectNames', 'label' => 'Project names'],
                ],
                'rows' => $rows,
                'search' => $q,
                'shownCount' => count($rows),
                'limit' => $limit,
            ],
        ];
    }

    // -----------------------------------------------------
    // Show Team
    // -----------------------------------------------------

    public function show(array $args = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing team identifier',
                'Usage: teams:show <id|name>',
            );
        }

        $team = $this->resolveTeam((string) $identifier);

        if (!$team) {
            return $this->error(
                'Team not found',
                sprintf('No team found for "%s".', $identifier),
            );
        }

        return $this->teamShowPayload('Team', $team);
    }

    // -----------------------------------------------------
    // Add Team
    // -----------------------------------------------------

    public function add(array $flags = []): array
    {
        if ($flags !== []) {
            $result = $this->createTeamFromData([
                'name' => $flags['name'] ?? null,
                'users' => $flags['users'] ?? $flags['user'] ?? null,
            ]);

            if (!$result['success']) {
                return $this->error('Team could not be created', $result['error']);
            }

            return $this->teamShowPayload('Team created', $result['team']);
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
    // Update Team
    // -----------------------------------------------------

    public function update(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing team identifier',
                'Usage: teams:update <id|name> --name=',
            );
        }

        $team = $this->resolveTeam((string) $identifier);

        if (!$team) {
            return $this->error('Team not found', sprintf('No team found for "%s".', $identifier));
        }

        $changed = [];

        if (isset($flags['name'])) {
            $name = trim((string) $flags['name']);

            if (mb_strlen($name) < 2) {
                return $this->error('Invalid team name', 'Team name must be at least 2 characters.');
            }

            $team->setName($name);
            $changed[] = 'name';
        }

        if ($changed === []) {
            return $this->error(
                'Nothing to update',
                'Use --name= to rename the team. Use teams:add-user / teams:remove-user to manage users.',
            );
        }

        $this->em->flush();

        return $this->teamShowPayload('Team updated', $team);
    }

    // -----------------------------------------------------
    // Delete Team
    // -----------------------------------------------------

    public function delete(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing team identifier',
                'Usage: teams:delete <id|name> --force',
            );
        }

        if (!isset($flags['force'])) {
            return $this->error(
                'Delete requires confirmation',
                sprintf('Run: teams:delete %s --force', $identifier),
            );
        }

        $team = $this->resolveTeam((string) $identifier);

        if (!$team) {
            return $this->error('Team not found', sprintf('No team found for "%s".', $identifier));
        }

        $name = $team->getName();

        $this->em->remove($team);
        $this->em->flush();

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'delete',
                'title' => 'Team deleted',
                'message' => sprintf('Team "%s" was deleted.', $name),
            ],
        ];
    }

    // -----------------------------------------------------
    // Add User To Team
    // -----------------------------------------------------

    public function addUser(array $args = [], array $flags = []): array
    {
        $teamIdentifier = $args[0] ?? null;
        $userIdentifier = $args[1] ?? $flags['user'] ?? null;

        if (!$teamIdentifier || !$userIdentifier) {
            return $this->error('Missing input', 'Usage: teams:add-user <teamId|name> <userId|email>');
        }

        $team = $this->resolveTeam((string) $teamIdentifier);
        $user = $this->resolveUser((string) $userIdentifier);

        if (!$team) {
            return $this->error('Team not found', sprintf('No team found for "%s".', $teamIdentifier));
        }

        if (!$user) {
            return $this->error('User not found', sprintf('No user found for "%s".', $userIdentifier));
        }

        if ($team->getUsers()->contains($user)) {
            return $this->teamShowPayload('User already in team', $team);
        }

        $team->addUser($user);
        $this->em->flush();

        return $this->teamShowPayload('User added to team', $team);
    }

    // -----------------------------------------------------
    // Remove User From Team
    // -----------------------------------------------------

    public function removeUser(array $args = [], array $flags = []): array
    {
        $teamIdentifier = $args[0] ?? null;
        $userIdentifier = $args[1] ?? $flags['user'] ?? null;

        if (!$teamIdentifier || !$userIdentifier) {
            return $this->error('Missing input', 'Usage: teams:remove-user <teamId|name> <userId|email>');
        }

        $team = $this->resolveTeam((string) $teamIdentifier);
        $user = $this->resolveUser((string) $userIdentifier);

        if (!$team) {
            return $this->error('Team not found', sprintf('No team found for "%s".', $teamIdentifier));
        }

        if (!$user) {
            return $this->error('User not found', sprintf('No user found for "%s".', $userIdentifier));
        }

        if (!$team->getUsers()->contains($user)) {
            return $this->teamShowPayload('User was not in team', $team);
        }

        $team->removeUser($user);
        $this->em->flush();

        return $this->teamShowPayload('User removed from team', $team);
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
                'output' => 'Team creation cancelled.',
                'await' => false,
            ];
        }

        if (in_array($lower, ['team.a', 't.a', 'teams:add'], true)) {
            $this->state->clear($userId);

            return $this->add();
        }

        return $this->handleAddWizard($userId, $state, $raw);
    }

    // -----------------------------------------------------
    // Team Add Wizard
    // -----------------------------------------------------

    private function handleAddWizard(string $userId, array $state, string $input): array
    {
        $step = (int) ($state['step'] ?? 1);
        $data = $state['data'] ?? [];

        if ($step === count(self::ADD_STEPS) + 1) {
            if (!in_array(mb_strtolower(trim($input)), ['yes', 'y'], true)) {
                $this->state->clear($userId);

                return [
                    'success' => false,
                    'output' => 'Team creation aborted.',
                ];
            }

            $result = $this->createTeamFromData($data);
            $this->state->clear($userId);

            if (!$result['success']) {
                return $this->error('Team could not be created', $result['error']);
            }

            return $this->teamShowPayload('Team created', $result['team']);
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
            case 'name':
                if (mb_strlen($value) < 2) {
                    $error = 'Team name must be at least 2 characters.';
                    break;
                }

                $data['name'] = $value;
                break;

            case 'users':
                if ($value === '') {
                    $data['users'] = '';
                    $data['userLabels'] = [];
                    break;
                }

                $users = $this->resolveUsers($value);

                if ($users === []) {
                    $error = 'No users matched that input. Use existing ids or emails, comma separated.';
                    break;
                }

                $data['users'] = implode(',', array_map(static fn (User $user): string => (string) $user->getId(), $users));
                $data['userLabels'] = array_map(fn (User $user): string => $this->formatUserLabel($user), $users);
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

        $confirmStep = count(self::ADD_STEPS) + 1;

        $this->saveWizardStep($userId, $confirmStep, $data);

        return [
            'success' => true,
            'await' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'confirm',
                'title' => sprintf('Step %d of %d — Confirm team creation', $confirmStep, $confirmStep),
                'description' => 'Type "yes" or "y" to confirm. Any other input aborts.',
                'fields' => [
                    ['label' => 'Name', 'value' => $data['name'] ?? '—'],
                    ['label' => 'Users', 'value' => !empty($data['userLabels']) ? implode(', ', $data['userLabels']) : 'none'],
                ],
            ],
        ];
    }

    // -----------------------------------------------------
    // Create Team From Data
    // -----------------------------------------------------

    /** @return array{success: bool, team?: Team, error?: string} */
    private function createTeamFromData(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if (mb_strlen($name) < 2) {
            return ['success' => false, 'error' => 'Team name is required and must be at least 2 characters.'];
        }

        $usersRaw = trim((string) ($data['users'] ?? ''));
        $users = $usersRaw === '' ? [] : $this->resolveUsers($usersRaw);

        $team = new Team();
        $team->setName($name);

        foreach ($users as $user) {
            $team->addUser($user);
        }

        $this->em->persist($team);
        $this->em->flush();

        return ['success' => true, 'team' => $team];
    }

    // -----------------------------------------------------
    // Team Show Payload
    // -----------------------------------------------------

    private function teamShowPayload(string $title, Team $team): array
    {
        $users = array_map(
            fn (User $user): array => [
                'id' => $user->getId(),
                'label' => $this->formatUserLabel($user),
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : $user->getUserIdentifier(),
            ],
            $team->getUsers()->toArray(),
        );

        $projects = array_map(
            static fn (Project $project): array => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
            $team->getProjects()->toArray(),
        );

        $fields = [
            ['label' => 'ID', 'value' => $team->getId()],
            ['label' => 'Name', 'value' => $team->getName()],
            ['label' => 'Users', 'value' => sprintf('(%d)', count($users))],
            ['label' => 'Projects', 'value' => sprintf('(%d)', count($projects))],
        ];

        foreach ($users as $user) {
            $fields[] = [
                'label' => sprintf('user #%s', $user['id']),
                'value' => $user['label'],
            ];
        }

        foreach ($projects as $project) {
            $fields[] = [
                'label' => sprintf('project #%s', $project['id']),
                'value' => $project['name'],
            ];
        }

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'show',
                'resource' => 'teams',
                'title' => $title,
                'fields' => $fields,
                'team' => $team,
                'users' => $users,
                'projects' => $projects,
            ],
        ];
    }

    // -----------------------------------------------------
    // Team List Row
    // -----------------------------------------------------

    private function teamListRow(Team $team): array
    {
        $projectNames = array_map(
            static fn (Project $project): string => (string) $project->getName(),
            $team->getProjects()->toArray(),
        );

        return [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'usersCount' => $team->getUsers()->count(),
            'projectsCount' => $team->getProjects()->count(),
            'projectNames' => $projectNames !== [] ? implode(', ', $projectNames) : '—',
        ];
    }

    // -----------------------------------------------------
    // Step View
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

        if ($stepDefinition['key'] === 'users') {
            $vars['usersPreview'] = $this->getUserPreview();
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
    // Resolve Team
    // -----------------------------------------------------

    private function resolveTeam(string $raw): ?Team
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $team = $this->teamRepository->find((int) $raw);
            if ($team) {
                return $team;
            }
        }

        return $this->teamRepository->findOneBy(['name' => $raw]);
    }

    // -----------------------------------------------------
    // Resolve User
    // -----------------------------------------------------

    private function resolveUser(string $raw): ?User
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $user = $this->userRepository->find((int) $raw);
            if ($user) {
                return $user;
            }
        }

        $user = $this->userRepository->findOneBy(['email' => $raw]);

        if ($user) {
            return $user;
        }

        return $this->userRepository->findOneBy(['username' => $raw]);
    }

    // -----------------------------------------------------
    // Resolve Users
    // -----------------------------------------------------

    /** @return User[] */
    private function resolveUsers(string $csv): array
    {
        $users = [];
        $parts = array_filter(array_map('trim', explode(',', $csv)));

        foreach ($parts as $part) {
            $user = $this->resolveUser($part);

            if ($user && !in_array($user, $users, true)) {
                $users[] = $user;
            }
        }

        return $users;
    }

    // -----------------------------------------------------
    // Preview Helpers
    // -----------------------------------------------------

    private function getUserPreview(): array
    {
        $users = $this->userRepository->findBy([], ['id' => 'DESC'], 5);

        return array_map(
            fn (User $user): array => [
                'id' => $user->getId(),
                'label' => $this->formatUserLabel($user),
            ],
            $users,
        );
    }

    // -----------------------------------------------------
    // Format User Label
    // -----------------------------------------------------

    private function formatUserLabel(User $user): string
    {
        $id = $user->getId();
        $identifier = $user->getUserIdentifier();
        $email = method_exists($user, 'getEmail') ? $user->getEmail() : $identifier;

        if ($email && $email !== $identifier) {
            return sprintf('#%d %s <%s>', $id, $identifier, $email);
        }

        return sprintf('#%d %s', $id, $identifier);
    }
}
