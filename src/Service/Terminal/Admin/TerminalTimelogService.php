<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Timelog;
use App\Repository\TimelogRepository;
use App\Repository\TodoRepository;
use App\Repository\UserRepository;
use App\Service\Terminal\TerminalStateService;
use App\Service\UserProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalTimelogService
{
    private const VIEW = 'terminals/admin/terminal_commands/timelogs.html.twig';
    private const STEPS = [
        'user' => 'User ID',
        'todo' => 'Todo ID',
        'date' => 'Date (YYYY-MM-DD)',
        'minutes' => 'Total minutes (1–1440)',
        'description' => 'Description (optional)',
    ];

    public function __construct(
        private readonly TimelogRepository $logs,
        private readonly TodoRepository $todos,
        private readonly UserRepository $users,
        private readonly UserProjectService $access,
        private readonly EntityManagerInterface $em,
        private readonly TerminalStateService $state,
        private readonly Security $security,
    ) {
    }

    public function list(array $flags = []): array
    {
        try {
            $limit = isset($flags['limit']) ? $this->integer($flags['limit'], 'limit', 1, 500) : 50;
            $user = isset($flags['user']) ? $this->users->find($this->integer($flags['user'], 'user')) : null;
            $todo = isset($flags['todo']) ? $this->todos->find($this->integer($flags['todo'], 'todo')) : null;
            $projectId = isset($flags['project']) ? $this->integer($flags['project'], 'project') : null;
            $date = isset($flags['date']) ? $this->date($flags['date']) : null;
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
        if ((isset($flags['user']) && !$user) || (isset($flags['todo']) && !$todo)) {
            return $this->error('User or Todo not found.');
        }
        $rows = array_map(static fn (Timelog $log): array => [
            'id' => $log->getId(),
            'user' => $log->getUser()?->getUserIdentifier(),
            'todo' => $log->getTodo()?->getName(),
            'project' => $log->getTodo()?->getProject()?->getName(),
            'date' => $log->getDate()?->format('Y-m-d'),
            'minutes' => $log->getTotalMinutes(),
            'description' => $log->getDescription(),
        ], $this->logs->findForTerminalList($user, $todo, $projectId, $date, $limit));
        return $this->response('list', 'Timelogs', [
            'columns' => array_map(static fn (string $key): array => ['key' => $key, 'label' => ucfirst($key)],
                ['id', 'user', 'todo', 'project', 'date', 'minutes', 'description']),
            'rows' => $rows, 'shownCount' => count($rows), 'limit' => $limit,
        ]);
    }

    public function show(array $args = []): array
    {
        try {
            return $this->details($this->find($args));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function add(array $flags = []): array
    {
        if ($flags === []) {
            $this->state->set($this->actor(), ['mode' => 'timelogs:add', 'data' => []]);
            return $this->prompt([]);
        }
        try {
            $log = new Timelog();
            $this->apply($log, $flags);
            $this->em->persist($log);
            $this->em->flush();
            return $this->details($log, 'Timelog created');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(array $args = [], array $flags = []): array
    {
        try {
            $log = $this->find($args);
            if ($flags === []) {
                return $this->error('Use --minutes=, --date= or --description= to update a timelog.');
            }
            if (array_diff(array_keys($flags), ['minutes', 'date', 'description']) !== []) {
                return $this->error('Only minutes, date and description can be updated.');
            }
            $date = isset($flags['date']) ? $this->date($flags['date']) : $log->getDate();
            $minutes = isset($flags['minutes']) ? $this->integer($flags['minutes'], 'minutes', 1, 1440) : $log->getTotalMinutes();
            $description = isset($flags['description']) ? $this->description($flags['description']) : $log->getDescription();
            $log->setDate($date)->setTotalMinutes($minutes)->setDescription($description);
            $this->em->flush();
            return $this->details($log, 'Timelog updated');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete(array $args = []): array
    {
        try {
            $log = $this->find($args);
            $this->state->set($this->actor(), ['mode' => 'timelogs:delete', 'id' => $log->getId()]);
            $result = $this->details($log, 'Delete this timelog? Type yes to confirm or cancel to abort.');
            $result['await'] = true;
            return $result;
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }
    }

    public function handleInteractive(string $input): ?array
    {
        $state = $this->state->get($this->actor());
        if (!in_array($state['mode'] ?? '', ['timelogs:add', 'timelogs:delete'], true)) {
            return null;
        }
        if (strtolower($input) === 'cancel') {
            $this->state->clear($this->actor());
            return ['success' => true, 'output' => 'Timelog operation cancelled.'];
        }
        if ($state['mode'] === 'timelogs:delete') {
            if (strtolower($input) !== 'yes') {
                return ['success' => false, 'await' => true, 'output' => 'Type yes to delete or cancel to abort.'];
            }
            $log = $this->logs->find($state['id']);
            $this->state->clear($this->actor());
            if (!$log) {
                return $this->error('Timelog no longer exists.');
            }
            $this->em->remove($log);
            $this->em->flush();
            return ['success' => true, 'output' => 'Timelog deleted.'];
        }
        $data = $state['data'];
        $key = array_keys(self::STEPS)[count($data)] ?? null;
        if ($key !== null) {
            try {
                $value = match ($key) {
                    'user', 'todo' => $this->integer($input, $key),
                    'minutes' => $this->integer($input, $key, 1, 1440),
                    'date' => $this->date($input)->format('Y-m-d'),
                    'description' => $this->description($input),
                };
                if ($key === 'user' && !$this->users->find($value)) {
                    throw new \InvalidArgumentException('User not found.');
                }
                if ($key === 'todo') {
                    $todo = $this->todos->find($value);
                    $user = $this->users->find($data['user']);
                    if (!$todo || !$user || !$this->access->canUserAccessTodo($user, $todo)) {
                        throw new \InvalidArgumentException('Todo not found or user has no project access.');
                    }
                }
                $data[$key] = $value;
            } catch (\InvalidArgumentException $e) {
                return $this->prompt($data, $e->getMessage());
            }
            $this->state->set($this->actor(), ['mode' => 'timelogs:add', 'data' => $data]);
            return $this->prompt($data);
        }
        if (strtolower($input) !== 'yes') {
            return $this->prompt($data, 'Type yes to save or cancel to abort.');
        }
        $this->state->clear($this->actor());
        return $this->add($data);
    }

    private function apply(Timelog $log, array $data): void
    {
        if (array_diff(array_keys($data), array_keys(self::STEPS)) !== []) {
            throw new \InvalidArgumentException('Use --user, --todo, --date, --minutes and --description.');
        }
        $user = $this->users->find($this->integer($data['user'] ?? '', 'user'));
        $todo = $this->todos->find($this->integer($data['todo'] ?? '', 'todo'));
        if (!$user || !$todo || !$this->access->canUserAccessTodo($user, $todo)) {
            throw new \InvalidArgumentException('User/Todo not found or user has no project access.');
        }
        $date = $this->date($data['date'] ?? '');
        $minutes = $this->integer($data['minutes'] ?? '', 'minutes', 1, 1440);
        $description = $this->description($data['description'] ?? '');
        $log->setUser($user)->setTodo($todo)->setDate($date)->setTotalMinutes($minutes)->setDescription($description);
    }

    private function find(array $args): Timelog
    {
        $id = $this->integer($args[0] ?? '', 'timelog ID');
        return $this->logs->find($id) ?? throw new \InvalidArgumentException('Timelog not found.');
    }

    private function integer(mixed $value, string $name, int $min = 1, int $max = PHP_INT_MAX): int
    {
        if ((!is_string($value) && !is_int($value)) || !ctype_digit((string) $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) === false) {
            throw new \InvalidArgumentException(sprintf('%s must be an integer between %d and %d.', $name, $min, $max));
        }
        return (int) $value;
    }

    private function date(mixed $value): \DateTime
    {
        $date = is_string($value) ? \DateTime::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Use a valid date in YYYY-MM-DD format.');
        }
        return $date;
    }

    private function description(mixed $value): string
    {
        if (!is_string($value) || mb_strlen($value) > 255) {
            throw new \InvalidArgumentException('Description must be at most 255 characters.');
        }
        return trim($value);
    }

    private function actor(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'guest';
    }

    private function prompt(array $data, ?string $error = null): array
    {
        if (count($data) === count(self::STEPS)) {
            return $this->response('confirm', 'Create this timelog? Type yes to save or cancel to abort.', [
                'fields' => array_map(static fn (string $key): array => ['label' => self::STEPS[$key], 'value' => $data[$key]], array_keys(self::STEPS)),
                'message' => $error,
            ]) + ['await' => true];
        }
        return ['success' => $error === null, 'await' => true,
            'view' => 'terminals/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars' => ['label' => array_values(self::STEPS)[count($data)], 'step' => count($data) + 1,
                'total' => count(self::STEPS), 'error' => $error],
        ];
    }

    private function details(Timelog $log, string $title = 'Timelog'): array
    {
        $fields = [
            ['label' => 'ID', 'value' => $log->getId()],
            ['label' => 'Date', 'value' => $log->getDate()?->format('Y-m-d')],
            ['label' => 'Time', 'value' => sprintf('%dh %dm', $log->getHours(), $log->getMinutes())],
            ['label' => 'Description', 'value' => $log->getDescription()],
        ];
        foreach (['user' => $log->getUser(), 'todo' => $log->getTodo(), 'project' => $log->getTodo()?->getProject()] as $type => $entity) {
            $fields[] = ['label' => $entity ? $type . ' #' . $entity->getId() : ucfirst($type),
                'value' => $entity ? ($type === 'user' ? $entity->getUserIdentifier() : $entity->getName()) : null];
        }
        return $this->response('show', $title, ['fields' => $fields]);
    }

    private function response(string $view, string $title, array $vars): array
    {
        return ['success' => true, 'view' => self::VIEW,
            'vars' => ['view' => $view, 'resource' => 'timelogs', 'title' => $title] + $vars];
    }

    private function error(string $message): array
    {
        $result = $this->response('error', 'Timelog command failed', ['message' => $message]);
        $result['success'] = false;
        return $result;
    }
}
