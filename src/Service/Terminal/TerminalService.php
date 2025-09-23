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
    public function __construct(
        private Security $security,
        private UserTerminalService $userModule, // Users module (Entity + Repository logic)
    ) {}

    /** @return array{output:string, success:bool} */
    public function execute(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['output' => 'No command.', 'success' => false];
        }

        // First token is the command "name" (e.g., "users:list")
        $first   = strtok($input, " \t") ?: '';
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        // Allowlists (expand as you add more)
        $adminCommands = [
            'help', 'ping',
            'users:list', 'users:show',
            'users:add',
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

    /** @return array{output:string, success:bool} */
    private function handleAdmin(string $input): array
    {
        // Tokenize (keep original tokens to pass to modules)
        $tokens = preg_split('/\s+/', trim($input)) ?: [];
        $cmd    = array_shift($tokens) ?? '';

        // Simple router: delegate domain-specific commands to their modules
        return match ($cmd) {
            'help'       => ['output' => 'Available (admin): ping, users:list, users:show', 'success' => true],
            'ping'       => ['output' => 'pong (admin)', 'success' => true],

            // Users domain
            'users:list' => $this->userModule->listCommand($tokens),
            'users:show' => $this->userModule->showCommand($tokens),
            'users:add'  => $this->userModule->addCommand($tokens),

            default      => ['output' => 'Unhandled admin command', 'success' => false],
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
