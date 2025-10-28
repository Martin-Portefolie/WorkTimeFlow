<?php
namespace App\Service\Terminal;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Single entrypoint/router for the terminal.
 * - Decides admin vs. user
 * - Validates command name
 * - Delegates domain-specific work (users, clients, projects, todos) to their services
 */
final class TerminalService
{
    /** @var array<string,string> */
    private const ALIASES = [
        'u.l'=>'users:list','u.s'=>'users:show','u.a'=>'users:add','u.u'=>'users:update',
        'u.fp'=>'users:forgot-password','u.off'=>'users:deactivate','u.on'=>'users:activate','u.del'=>'users:delete',
    ];

    public function __construct(
        private Security $security,
        private UserTerminalService $userModule, // Users module (Entity + Repository logic)
    ) {}


    /**
     * Return shapes:
     *  - ['output' => string, 'success' => bool]
     *  - ['view' => 'twig/path.html.twig', 'vars' => [...], 'success' => bool]
     */
    public function execute(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['output' => 'No command.', 'success' => false];
        }
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $tokens = preg_split('/\s+/', $input) ?: [];
        $first  = $tokens[0] ?? '';

        if (isset(self::ALIASES[$first])) {
            // replace the first token with its canonical command
            $tokens[0] = self::ALIASES[$first];
            $input     = implode(' ', $tokens);
            $first     = $tokens[0];
        }

        if (in_array(strtolower($first), ['clear', 'cls'], true)) {
            return ['output' => '', 'success' => true];
        }

        // Allowlists (expand as you add more)
        $adminCommands = [
            'help',
            'ping',
            'users:list',
            'users:show',
            'users:add',
            'users:update',
            'users:forgot-password',
            'users:delete',
            'users:deactivate',
            'users:activate',

        ];
        $userCommands  = ['help', 'ping'];

        // Tolerate client-side-only commands if they accidentally POST here
        if (in_array(strtolower($first), ['clear', 'cls'], true)) {
            return ['output' => '', 'success' => true];
        }

        $known = $isAdmin ? $adminCommands : $userCommands;
        if (!in_array($first, $known, true)) {
            return ['output' => sprintf('%s is not viable', htmlspecialchars($first)), 'success' => false];
        }

        // Dispatch
        return $isAdmin ? $this->handleAdmin($input) : $this->handleUser($input);
    }



    private function handleAdmin(string $input): array
    {
        $tokens = preg_split('/\s+/', trim($input)) ?: [];
        $cmd    = array_shift($tokens) ?? '';

        return match ($cmd) {
            'help' => [
                'view' => 'partials/_help_admin.html.twig',
                'vars' => [
                    'aliases' => [
                        ['alias'=>'u.l',  'cmd'=>'users:list',            'note'=>'list users (default: active only; flags: --q=search, --is-active=active|inactive|all)'],
                        ['alias'=>'u.s',  'cmd'=>'users:show',            'note'=>'show one by id/email'],
                        ['alias'=>'u.a',  'cmd'=>'users:add',             'note'=>'create user & email credentials'],
                        ['alias'=>'u.u',  'cmd'=>'users:update',          'note'=>'update email/username/roles'],
                        ['alias'=>'u.fp', 'cmd'=>'users:forgot-password', 'note'=>'set temp password & email user'],
                        ['alias'=>'u.del','cmd'=>'users:delete',          'note'=>'hard delete ()'],
                        ['alias'=>'u.off','cmd'=>'users:deactivate',      'note'=>'disable login (soft off)'],
                        ['alias'=>'u.on', 'cmd'=>'users:activate',        'note'=>'enable login'],
                    ],
                ],
                'success' => true,
            ],
            'ping'                   => ['output'=>'pong (admin)','success'=>true],
            'users:list'             => $this->userModule->listCommand($tokens),
            'users:show'             => $this->userModule->showCommand($tokens),
            'users:add'              => $this->userModule->addCommand($tokens),
            'users:update'           => $this->userModule->updateCommand($tokens),
            'users:forgot-password'  => $this->userModule->forgotPasswordCommand($tokens),
            'users:delete'           => $this->userModule->deleteCommand($tokens),
            'users:deactivate'       => $this->userModule->deactivateCommand($tokens),
            'users:activate'         => $this->userModule->activateCommand($tokens),
            default                  => ['output'=>'Unhandled admin command','success'=>false],
        };
    }



    /** @return array{output:string, success:bool} */
    private function handleUser(string $input): array
    {
        // For now, simple user-side commands; later you can add a ProfileTerminalService
        return match ($input) {
            'help' => ['output' => 'Available (user): ping', 'success' => true],
            'ping' => ['output' => 'pong (user)', 'success' => true],
            default => ['output' => 'Unhandled user command', 'success' => false],
        };
    }
}
