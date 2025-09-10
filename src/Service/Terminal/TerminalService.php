<?php

namespace App\Service\Terminal;

use Symfony\Bundle\SecurityBundle\Security;

final class TerminalService
{
    public function __construct(
        private Security   $security,
        private ArgsParser $userService,
    ) {}

    /** @return array{output:string, success:bool} */
    public function execute(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['output' => 'No command.', 'success' => false];
        }

        // 1) extract the command name (first token)
        $name    = strtok($input, " \t") ?: '';
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        // 2) known commands per role (expand as you add more)
        $adminCommands = ['help', 'ping']; // add: users:list, users:add, ...
        $userCommands  = ['help', 'ping'];

        $known = $isAdmin ? $adminCommands : $userCommands;

        // 3) tolerate client-side commands if they slip through
        $clientOnly = ['clear', 'cls'];
        if (in_array(strtolower($name), $clientOnly, true)) {
            return ['output' => '', 'success' => true]; // no-op
        }

        // 4) reject unknown
        if (!in_array($name, $known, true)) {
            return ['output' => sprintf('%s is not viable', htmlspecialchars($name)), 'success' => false];
        }

        // 5) dispatch
        return $isAdmin ? $this->handleAdmin($input) : $this->userService->handle($input);
    }

    /** @return array{output:string, success:bool} */
    private function handleAdmin(string $input): array
    {
        return match ($input) {
            'help' => ['output' => 'Available (admin): ping', 'success' => true],
            'ping' => ['output' => 'pong (admin)', 'success' => true],
            default => ['output' => 'Unhandled admin command', 'success' => false], // should not hit
        };
    }
}
