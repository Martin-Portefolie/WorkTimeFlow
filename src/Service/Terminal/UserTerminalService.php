<?php
namespace App\Service\Terminal;

use App\Repository\UserRepository;

/**
 * All user-related terminal commands live here.
 * TerminalService routes here for commands starting with "users:".
 */
final class UserTerminalService
{
    public function __construct(private UserRepository $users) {}

    /**
     * users:list [limit] [--q=search]
     *
     * Examples:
     *  - users:list
     *  - users:list 25
     *  - users:list --q=anna
     *
     * @param array<int,string> $tokens remaining tokens after "users:list"
     * @return array{output:string, success:bool}
     */
    public function listCommand(array $tokens): array
    {
        // Split tokens into positional args + flags (via ArgsParser)
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        // Positional arg #0 can be a numeric limit; default to 50
        $limit = (isset($args[0]) && ctype_digit($args[0])) ? (int) $args[0] : 50;

        // Optional case-insensitive search across email/username
        $q = isset($flags['q']) ? (string) $flags['q'] : null;

        $rows = $this->users->fetchListRowsSearched($limit, $q);
        if (!$rows) {
            return ['output' => 'No users found.', 'success' => true];
        }

        // Build compact table-like lines
        $lines = array_map(
            static fn (array $r) => sprintf(
                '%d | %s | %s | %s',
                $r['id'],
                $r['email'],
                (string) ($r['username'] ?? ''),
                implode(',', $r['roles'])
            ),
            $rows
        );

        // Join with <br> so the terminal renders each on its own line
        return ['output' => implode('<br>', $lines), 'success' => true];
    }

    /**
     * users:show <id|email|username>
     *
     * Examples:
     *  - users:show 3
     *  - users:show alice@example.com
     *  - users:show Alice
     *
     * @param array<int,string> $tokens remaining tokens after "users:show"
     * @return array{output:string, success:bool}
     */
    public function showCommand(array $tokens): array
    {
        // Must have exactly one identifier token
        if (count($tokens) !== 1) {
            return ['output' => 'Usage: users:show <id|email|username>', 'success' => false];
        }

        $key = $tokens[0];
        $u = $this->users->findOneByIdEmailOrUsername($key);

        if (!$u) {
            return ['output' => 'User not found.', 'success' => false];
        }

        // Escape user-provided strings to avoid HTML injection in terminal
        $email    = htmlspecialchars((string) $u->getEmail(),    ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string) $u->getUsername(), ENT_QUOTES, 'UTF-8');

        $out = sprintf(
            "id: %d<br>email: %s<br>username: %s<br>roles: %s",
            $u->getId(),
            $email,
            $username,
            implode(',', $u->getRoles())
        );

        return ['output' => $out, 'success' => true];
    }

    /**
     * Optional: profile-side commands (non-admin) can still live here.
     * TerminalService calls this when routing a *user* (non-admin) request.
     */
    public function handle(string $input): array
    {
        return match ($input) {
            'help' => ['output' => 'Available (user): ping', 'success' => true],
            'ping' => ['output' => 'pong (user)', 'success' => true],
            default => ['output' => 'Unhandled user command', 'success' => false],
        };
    }
}
