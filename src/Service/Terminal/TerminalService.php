<?php
namespace App\Service\Terminal;

use App\Service\Terminal\Admin\ClientTerminalService;
use App\Service\Terminal\Admin\UserTerminalService;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalService
{
    /** @var array<string,string> */
    private const ALIASES = [
        // Users
        'u.l'=>'users:list','u.s'=>'users:show','u.a'=>'users:add','u.u'=>'users:update',
        'u.fp'=>'users:forgot-password','u.off'=>'users:deactivate','u.on'=>'users:activate','u.del'=>'users:delete',
        // Clients
        'c.l'=>'clients:list','c.s'=>'clients:show','c.a'=>'clients:add','c.u'=>'clients:update','c.del'=>'clients:delete',
    ];

    public function __construct(
        private Security $security,
        private UserTerminalService $userModule,
        private ClientTerminalService $clientModule,
    ) {}

    private function isAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    /**
     * Return shapes:
     *  - ['output' => string, 'success' => bool]
     *  - ['view' => 'twig/path.html.twig', 'vars' => [...], 'success' => bool]
     *  - may also include 'await' => true during interactive prompts
     */
    public function execute(string $input): array
    {
        // keep raw as-trimmed; blank is meaningful for wizard steps
        $raw = trim($input);

        // Fast path: clear screen handled locally
        if (in_array(strtolower($raw), ['clear', 'cls'], true)) {
            return ['output' => '', 'success' => true];
        }

        $isAdmin = $this->isAdmin();

        // ── NEW: let any active wizard consume the input (even if it's blank) ──
        if ($isAdmin) {
            if ($resp = $this->userModule->handleInteractive($raw))   { return $resp; }
            if ($resp = $this->clientModule->handleInteractive($raw)) { return $resp; }
        }

        // At this point we know no wizard is active / interested.
        if ($raw === '') {
            return ['output' => 'No command.', 'success' => false];
        }

        // Tokenize + resolve aliases
        $tokens = preg_split('/\s+/', $raw) ?: [];
        $first  = $tokens[0] ?? '';
        if (isset(self::ALIASES[$first])) {
            $tokens[0] = self::ALIASES[$first];
            $raw       = implode(' ', $tokens);
            $first     = $tokens[0];
        }

        // Known-commands allowlist
        $adminCommands = [
            'help','ping',
            'users:list','users:show','users:add','users:update','users:forgot-password',
            'users:delete','users:deactivate','users:activate',
            'clients:list','clients:show','clients:add','clients:update','clients:delete',
        ];
        $userCommands  = ['help','ping'];
        $known = $isAdmin ? $adminCommands : $userCommands;

        // Dispatch known commands
        if (in_array($first, $known, true)) {
            return $isAdmin ? $this->handleAdmin($raw) : $this->handleUser($raw);
        }

        // If somehow not known and no wizard, fall back
        return ['output' => sprintf('%s is not viable', htmlspecialchars($first)), 'success' => false];
    }


    private function handleAdmin(string $input): array
    {
        $tokens = preg_split('/\s+/', trim($input)) ?: [];
        $cmd    = array_shift($tokens) ?? '';

        return match ($cmd) {
            'help' => [
                'view' => 'terminal/admin/_help_admin.html.twig',
                'vars' => [
                    'aliases' => [
                        // users
                        ['alias'=>'u.l',  'cmd'=>'users:list',            'note'=>'list users (flags: --q= --is-active=active|inactive|all)'],
                        ['alias'=>'u.s',  'cmd'=>'users:show',            'note'=>'show one by id/email'],
                        ['alias'=>'u.a',  'cmd'=>'users:add',             'note'=>'create user & email credentials'],
                        ['alias'=>'u.u',  'cmd'=>'users:update',          'note'=>'update email/username/roles'],
                        ['alias'=>'u.fp', 'cmd'=>'users:forgot-password', 'note'=>'set temp password & email user'],
                        ['alias'=>'u.del','cmd'=>'users:delete',          'note'=>'hard delete'],
                        ['alias'=>'u.off','cmd'=>'users:deactivate',      'note'=>'disable login'],
                        ['alias'=>'u.on', 'cmd'=>'users:activate',        'note'=>'enable login'],
                        // clients
                        ['alias'=>'c.l',  'cmd'=>'clients:list',   'note'=>'list clients (flags: --q=)'],
                        ['alias'=>'c.s',  'cmd'=>'clients:show',   'note'=>'show one by id/name/email'],
                        ['alias'=>'c.a',  'cmd'=>'clients:add',    'note'=>'interactive — press Enter to skip, type "cancel" to abort'],
                        ['alias'=>'c.u',  'cmd'=>'clients:update', 'note'=>'update fields'],
                        ['alias'=>'c.del','cmd'=>'clients:delete', 'note'=>'hard delete'],
                    ],
                ],
                'success' => true,
            ],

            // users
            'users:list'             => $this->userModule->listCommand($tokens),
            'users:show'             => $this->userModule->showCommand($tokens),
            'users:add'              => $this->userModule->addCommand($tokens),
            'users:update'           => $this->userModule->updateCommand($tokens),
            'users:forgot-password'  => $this->userModule->forgotPasswordCommand($tokens),
            'users:delete'           => $this->userModule->deleteCommand($tokens),
            'users:deactivate'       => $this->userModule->deactivateCommand($tokens),
            'users:activate'         => $this->userModule->activateCommand($tokens),

            // clients
            'clients:list'   => $this->clientModule->listCommand($tokens),
            'clients:show'   => $this->clientModule->showCommand($tokens),
            'clients:add'    => $this->clientModule->addCommand($tokens),   // starts interactive wizard (returns await=true)
            'clients:update' => $this->clientModule->updateCommand($tokens),
            'clients:delete' => $this->clientModule->deleteCommand($tokens),

            default => ['output'=>'Unhandled admin command','success'=>false],
        };
    }

    /** @return array{output:string, success:bool} */
    private function handleUser(string $input): array
    {
        return match (trim($input)) {
            'help' => ['output' => 'Available (user): ping', 'success' => true],
            'ping' => ['output' => 'pong (user)', 'success' => true],
            default => ['output' => 'Unhandled user command', 'success' => false],
        };
    }
}
