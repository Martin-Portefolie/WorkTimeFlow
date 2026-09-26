<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Todo;
use App\Repository\ProjectRepository;
use App\Repository\TodoRepository;

final class TerminalTodoService
{
    private const string VIEW = 'terminals/admin/terminal_commands/todos.html.twig';

    public function __construct(
        private readonly TodoRepository $todoRepository,
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    public function list(array $flags = []): array
    {
        $q = isset($flags['q']) ? trim((string) $flags['q']) : null;
        $limit = isset($flags['limit']) ? max(1, (int) $flags['limit']) : 50;

        $project = null;
        if (isset($flags['project'])) {
            $identifier = trim((string) $flags['project']);
            $project = ctype_digit($identifier)
                ? $this->projectRepository->find((int) $identifier)
                : $this->projectRepository->findOneBy(['name' => $identifier]);

            if (!$project) {
                return $this->error('Project not found', sprintf('No project found for "%s".', $identifier));
            }

        }

        $rows = array_map(
            static fn (Todo $todo): array => [
                'id' => $todo->getId(),
                'name' => $todo->getName(),
                'projectId' => $todo->getProject()?->getId(),
                'projectName' => $todo->getProject()?->getName(),
                'status' => $todo->getStatus()->getLabel(),
                'dateStart' => $todo->getDateStart()?->format('d/m/Y'),
                'dateEnd' => $todo->getDateEnd()?->format('d/m/Y'),
            ],
            $this->todoRepository->findForTerminalList($q, $project, $limit),
        );

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'list',
                'resource' => 'todos',
                'title' => 'Todos',
                'description' => 'Todos available to admin commands.',
                'columns' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'name', 'label' => 'Todo'],
                    ['key' => 'projectName', 'label' => 'Project'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'dateStart', 'label' => 'Start date'],
                    ['key' => 'dateEnd', 'label' => 'End date'],
                ],
                'rows' => $rows,
                'search' => $q,
                'shownCount' => count($rows),
                'limit' => $limit,
            ],
        ];
    }

    public function show(array $args = []): array
    {
        $identifier = trim((string) ($args[0] ?? ''));

        if ($identifier === '') {
            return $this->error('Missing todo identifier', 'Usage: todos:show <id|name>');
        }

        $todo = ctype_digit($identifier)
            ? $this->todoRepository->find((int) $identifier)
            : $this->todoRepository->findOneBy(['name' => $identifier]);

        if (!$todo) {
            return $this->error('Todo not found', sprintf('No todo found for "%s".', $identifier));
        }

        $fields = [
            ['label' => 'ID', 'value' => $todo->getId()],
            ['label' => 'Name', 'value' => $todo->getName()],
            ['label' => 'Status', 'value' => $todo->getStatus()->getLabel()],
            ['label' => 'Start date', 'value' => $todo->getDateStart()?->format('d/m/Y')],
            ['label' => 'End date', 'value' => $todo->getDateEnd()?->format('d/m/Y')],
        ];

        $project = $todo->getProject();
        $fields[] = [
            'label' => $project ? sprintf('project #%s', $project->getId()) : 'Project',
            'value' => $project?->getName(),
        ];

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'show',
                'resource' => 'todos',
                'title' => 'Todo',
                'fields' => $fields,
                'todo' => $todo,
            ],
        ];
    }

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
}
