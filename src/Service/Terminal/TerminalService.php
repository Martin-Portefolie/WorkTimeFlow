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
                'view' => 'terminal/admin/terminal_commands/_help_admin.html.twig',
                'vars' => [
                    'aliases' => [
                        // === USERS ===
                        [
                            'cmd'   => 'users:list',
                            'alias' => 'u.l',
                            'desc'  => 'List users',
                            'usage' => '',
                            'flags' => ['--q=<search>', '--is-active=active|inactive|all'],
                        ],
                        [
                            'cmd'   => 'users:show',
                            'alias' => 'u.s',
                            'desc'  => 'Show a user',
                            'usage' => '<id|email|username>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'users:add',
                            'alias' => 'u.a',
                            'desc'  => 'Interactive wizard — create a user (Enter to skip, "cancel" to abort)',
                            'usage' => '',   // interactive; no positional args
                            'flags' => [],   // interactive; no flags
                        ],
                        [
                            'cmd'   => 'users:update',
                            'alias' => 'u.u',
                            'desc'  => 'Update a user',
                            'usage' => '<id|email>',
                            'flags' => ['--email=<new_email>', '--username=<new_username>', '--roles=<ROLE_USER, ROLE_ADMIN>'],
                        ],
                        [
                            'cmd'   => 'users:forgot-password',
                            'alias' => 'u.fp',
                            'desc'  => 'Generate + email a temporary password',
                            'usage' => '<id|email>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'users:delete',
                            'alias' => 'u.del',
                            'desc'  => 'Delete a user',
                            'usage' => '<id|email>',
                            'flags' => ['--force'],
                        ],
                        [
                            'cmd'   => 'users:deactivate',
                            'alias' => 'u.off',
                            'desc'  => 'Disable login',
                            'usage' => '<id|email>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'users:activate',
                            'alias' => 'u.on',
                            'desc'  => 'Enable login',
                            'usage' => '<id|email>',
                            'flags' => [],
                        ],

                        // === CLIENTS ===
                        [
                            'cmd'   => 'clients:list',
                            'alias' => 'c.l',
                            'desc'  => 'List clients',
                            'usage' => '[limit]',
                            'flags' => ['--q=<search>'],
                        ],
                        [
                            'cmd'   => 'clients:show',
                            'alias' => 'c.s',
                            'desc'  => 'Show a client',
                            'usage' => '<id|name|email>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'clients:add',
                            'alias' => 'c.a',
                            'desc'  => 'Interactive wizard — add a client (Enter to skip, "cancel" to abort)',
                            'usage' => '',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'clients:update',
                            'alias' => 'c.u',
                            'desc'  => 'Update a client',
                            'usage' => '<id|name|email>',
                            'flags' => [
                                '--name=<new_name>', '--email=<new_email>', '--phone=<new_phone>',
                                '--contact=<person>', '--address=<street>', '--postal=<code>',
                                '--city=<city>', '--country=<country>',
                            ],
                        ],
                        [
                            'cmd'   => 'clients:delete',
                            'alias' => 'c.del',
                            'desc'  => 'Delete a client',
                            'usage' => '<id|name|email>',
                            'flags' => ['--force'],
                        ],
                    ]

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
