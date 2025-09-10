<?php

namespace App\Service\Terminal;

final class UserTerminalService
{
    /** @return array{output:string, success:bool} */
    public function handle(string $input): array
    {
        return match ($input) {
            'help' => ['output' => 'Available (user): ping', 'success' => true],
            'ping' => ['output' => 'pong (user)', 'success' => true],
            default => ['output' => 'Unhandled user command', 'success' => false],
        };
    }
}
