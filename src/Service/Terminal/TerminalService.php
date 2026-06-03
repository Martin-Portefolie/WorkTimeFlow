<?php

namespace App\Service\Terminal;

use App\Service\Terminal\Admin\ClientTerminalService;
use App\Service\Terminal\Admin\CompanyTerminalService;
use App\Service\Terminal\Admin\ProjectTerminalService;
use App\Service\Terminal\Admin\TeamTerminalService;
use App\Service\Terminal\Admin\TerminalUserService;
use App\Service\Terminal\Admin\TerminalClientService;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalService
{
    /** @var array<string,string> */
    private const ALIASES = [
        // Users
        'u.l'   => 'users:list',
        'u.s'   => 'users:show',
        'u.a'   => 'users:add',
        'u.u'   => 'users:update',
        'u.fp'  => 'users:forgot-password',
        'u.off' => 'users:deactivate',
        'u.on'  => 'users:activate',
        'u.del' => 'users:delete',

        // Clients
        'c.l'   => 'clients:list',
        'c.s'   => 'clients:show',
        'c.a'   => 'clients:add',
        'c.u'   => 'clients:update',
        'c.del' => 'clients:delete',

        // Company
        'co.s'  => 'company:show',
        'co.n'  => 'company:set-name',

        // Rates
        'r.l'   => 'rates:list',
        'r.a'   => 'rates:add',
        'r.u'   => 'rates:update',
        'r.del' => 'rates:delete',

        // Teams
        't.l'   => 'teams:list',
        't.s'   => 'teams:show',
        't.a'   => 'teams:add',
        't.u'   => 'teams:update',
        't.del' => 'teams:delete',
        't.au'  => 'teams:add-user',
        't.ru'  => 'teams:remove-user',

        // Projects
        'p.l'   => 'projects:list',
        'p.s'   => 'projects:show',
        'p.a'   => 'projects:add',
        'p.u'   => 'projects:update',
        'p.del' => 'projects:delete',
    ];

    public function __construct(
        private Security             $security,
//        private UserTerminalService  $userModule,
        private TerminalUserService $userModule,
        private TerminalClientService $clientModule,
        private CompanyTerminalService $companyModule,
        private TeamTerminalService $teamModule,
        private ProjectTerminalService $projectModule
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

        // ── Let any active wizard consume the input (even if it's blank) ──
        if ($isAdmin) {
            if ($resp = $this->userModule->handleInteractive($raw))   { return $resp; }
            if ($resp = $this->clientModule->handleInteractive($raw)) { return $resp; }
            if ($resp = $this->companyModule->handleInteractive($raw)) { return $resp; }
            if ($resp = $this->teamModule->handleInteractive($raw))   { return $resp; }
            if ($resp = $this->projectModule->handleInteractive($raw))   { return $resp; }
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

            // users
            'users:list','users:show','users:add','users:update','users:forgot-password',
            'users:delete','users:deactivate','users:activate',

            // clients
            'clients:list','clients:show','clients:add','clients:update','clients:delete',

            // company
            'company:show','company:set-name',

            // rates
            'rates:list','rates:add','rates:update','rates:delete',

            // teams
            'teams:list','teams:show','teams:add','teams:update','teams:delete',
            'teams:add-user','teams:remove-user',

            // Projects
            'projects:list','projects:show','projects:add','projects:update','projects:delete',
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
        $parsed = ArgsParser::parse($tokens);

        $args  = $parsed['args'];
        $flags = $parsed['flags'];

        return match ($cmd) {
            'help' => [
                'view' => 'terminals/admin/terminal_commands/_help_admin.html.twig',
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
                            'usage' => '',
                            'flags' => [],
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

                        // === COMPANY ===
                        [
                            'cmd'   => 'company:show',
                            'alias' => 'co.s',
                            'desc'  => 'Show our company (name, logo, rates)',
                            'usage' => '',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'company:set-name',
                            'alias' => 'co.n',
                            'desc'  => 'Create/update our company name (wizard or --name)',
                            'usage' => '[--name=<company_name>]',
                            'flags' => ['--name=<company_name>'],
                        ],

                        // === RATES ===
                        [
                            'cmd'   => 'rates:list',
                            'alias' => 'r.l',
                            'desc'  => 'List all rates for our company',
                            'usage' => '',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'rates:add',
                            'alias' => 'r.a',
                            'desc'  => 'Add a rate (flags or interactive wizard)',
                            'usage' => '[--name=<name> --value=<decimal>]',
                            'flags' => ['--name=<name>', '--value=<decimal>'],
                        ],
                        [
                            'cmd'   => 'rates:update',
                            'alias' => 'r.u',
                            'desc'  => 'Update a rate',
                            'usage' => '<id> [--name=<name> --value=<decimal>]',
                            'flags' => ['--name=<name>', '--value=<decimal>'],
                        ],
                        [
                            'cmd'   => 'rates:delete',
                            'alias' => 'r.del',
                            'desc'  => 'Delete a rate',
                            'usage' => '<id>',
                            'flags' => [],
                        ],

                        // === TEAMS ===
                        [
                            'cmd'   => 'teams:list',
                            'alias' => 't.l',
                            'desc'  => 'List all teams grouped by project',
                            'usage' => '',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'teams:show',
                            'alias' => 't.s',
                            'desc'  => 'Show a team, including users and projects',
                            'usage' => '<id|name>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'teams:add',
                            'alias' => 't.a',
                            'desc'  => 'Create a team (flags or interactive wizard)',
                            'usage' => '[--name= --project= --users=]',
                            'flags' => ['--name=<team_name>', '--project=<project_name>', '--users=<email1,email2>'],
                        ],
                        [
                            'cmd'   => 'teams:update',
                            'alias' => 't.u',
                            'desc'  => 'Rename a team',
                            'usage' => '<id> [--name=<new_name>]',
                            'flags' => ['--name=<new_name>'],
                        ],
                        [
                            'cmd'   => 'teams:delete',
                            'alias' => 't.del',
                            'desc'  => 'Delete a team',
                            'usage' => '<id>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'teams:add-user',
                            'alias' => 't.au',
                            'desc'  => 'Add user to team',
                            'usage' => '<teamId> <userId|email>',
                            'flags' => [],
                        ],
                        [
                            'cmd'   => 'teams:remove-user',
                            'alias' => 't.ru',
                            'desc'  => 'Remove user from team',
                            'usage' => '<teamId> <userId|email>',
                            'flags' => [],
                        ],

                        // === Projects ===
                        [
                            'cmd'   => 'projects:list',
                            'alias' => 'p.l',
                            'desc'  => 'List projects (overrun first, then newest)',
                            'usage' => '[filter]',
                            'flags' => ['--archived=active|archived|all'],
                        ],
                        [
                            'cmd'   => 'projects:show',
                            'alias' => 'p.s',
                            'desc'  => 'Show a project and its stats',
                            'usage' => '<id|name>',
                            'flags' => [],
                        ],

                    ],
                ],
                'success' => true,
            ],

            'ping' => [
                'output'  => 'pong (admin)',
                'success' => true,
            ],

            // users
            'users:list'             => $this->userModule->list($flags),
            'users:show'             => $this->userModule->show($args),
            'users:add'              => $this->userModule->add($flags),
            'users:update'           => $this->userModule->update($args, $flags),
            'users:forgot-password' => $this->userModule->forgotPassword($args, $flags),
            'users:delete'           => $this->userModule->delete($args, $flags),
            'users:deactivate' =>       $this->userModule->deactivate($args),
            'users:activate' =>         $this->userModule->activate($args),

            // clients
            'clients:list'   => $this->clientModule->list($flags),
            'clients:show'   => $this->clientModule->show($args),
            'clients:add'    => $this->clientModule->add($flags),
            'clients:update' => $this->clientModule->update($args, $flags),
            'clients:delete' => $this->clientModule->delete($args, $flags),

            // company
            'company:show'     => $this->companyModule->companyShowCommand($tokens),
            'company:set-name' => $this->companyModule->companySetNameCommand($tokens),

            // rates
            'rates:list'   => $this->companyModule->ratesListCommand($tokens),
            'rates:add'    => $this->companyModule->ratesAddCommand($tokens),
            'rates:update' => $this->companyModule->ratesUpdateCommand($tokens),
            'rates:delete' => $this->companyModule->ratesDeleteCommand($tokens),

            // teams
            'teams:list'        => $this->teamModule->listCommand($tokens),
            'teams:show'        => $this->teamModule->showCommand($tokens),
            'teams:add'         => $this->teamModule->addCommand($tokens),
            'teams:update'      => $this->teamModule->updateCommand($tokens),
            'teams:delete'      => $this->teamModule->deleteCommand($tokens),
            'teams:add-user'    => $this->teamModule->addUserCommand($tokens),
            'teams:remove-user' => $this->teamModule->removeUserCommand($tokens),

            // projects
            'projects:list'   => $this->projectModule->listCommand($tokens),
            'projects:show'   => $this->projectModule->showCommand($tokens),
            'projects:add'    => $this->projectModule->addCommand($tokens),
            'projects:update' => $this->projectModule->updateCommand($tokens),
            'projects:delete' => $this->projectModule->deleteCommand($tokens),

            default => ['output' => 'Unhandled admin command', 'success' => false],
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
