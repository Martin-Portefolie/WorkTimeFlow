<?php
namespace App\Service\Terminal;

use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use App\Entity\User;


/**
 * All user-related terminal commands live here.
 * TerminalService routes here for commands starting with "users:".
 */
final class UserTerminalService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ){}

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

        // Optional case-insensitive search across emails/username
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
                $r['emails'],
                ($r['username'] ?? ''),
                implode(',', $r['roles'])
            ),
            $rows
        );

        // Join with <br> so the terminal renders each on its own line
        return ['output' => implode('<br>', $lines), 'success' => true];
    }

    /**
     * users:show <id|emails|username>
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
            return ['output' => 'Usage: users:show <id|emails|username>', 'success' => false];
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
            "id: %d<br>emails: %s<br>username: %s<br>roles: %s",
            $u->getId(),
            $email,
            $username,
            implode(',', $u->getRoles())
        );

        return ['output' => $out, 'success' => true];
    }

    /**
     * users:add --emails=... [--username=...] [--password=...] [--roles=ROLE_USER,ROLE_ADMIN]
     *
     * - Generates a random password if --password is omitted (8 hex chars).
     * - Default roles to ROLE_USER if --roles omitted.
     * - Sends credentials emails using templates/emails/user_created.html.twig.
     * @throws RandomException
     */
    public function addCommand(array $tokens): array
    {
        ['args'=>$args, 'flags'=>$f] = ArgsParser::parse($tokens);

        $email    = isset($f['emails']) ? trim((string)$f['emails']) : '';
        $username = isset($f['username']) ? trim((string)$f['username']) : '';
        $password = isset($f['password']) ? (string)$f['password'] : null;
        $rolesStr = isset($f['roles']) ? (string)$f['roles'] : null;

        // Basic validation
        if ($email === '') {
            return ['output' => 'Usage: users:add --emails=alice@example.com [--username=Alice] [--password=Pass123] [--roles=ROLE_USER,ROLE_ADMIN]', 'success' => false];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['output' => 'Invalid emails format.', 'success' => false];
        }

        // Ensure emails is unique
        if ($this->users->findOneBy(['emails' => $email])) {
            return ['output' => 'Email already exists.', 'success' => false];
        }

        // Roles
        $roles = ['ROLE_USER'];
        if ($rolesStr !== null && $rolesStr !== '') {
            $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', $rolesStr)))));
            if (!$roles) { $roles = ['ROLE_USER']; }
        }

        // Password
        $plain = $password ?: bin2hex(random_bytes(4)); // 8 hex chars
        $user  = new User();
        $user->setEmail($email);
        if ($username !== '') {
            $user->setUsername($username);
        }
        $user->setRoles($roles);

        // Hash & set password
        $user->setPassword($this->hasher->hashPassword($user, $plain));

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return ['output' => 'Email already exists (unique constraint).', 'success' => false];
        } catch (\Throwable $e) {
            return ['output' => 'Failed to create user: '.$e->getMessage(), 'success' => false];
        }

        // Prepare & send emails (reuse your existing template)
        $loginUrl = $this->urls->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMsg = (new TemplatedEmail())
            ->from('no-reply@worktimeflow.local') // or your real domain
            ->to($user->getEmail())
            ->subject('Your WORKTIMEFLOW account')
            ->htmlTemplate('admin/emails/user_created.html.twig')
            ->context([
                'username'   => (string) $user->getUsername(),
                'user_email' => (string) $user->getEmail(),
                'password'   => $plain,
                'login_url'  => $loginUrl,
            ]);

        try {
            $this->mailer->send($emailMsg);
            $sent = true;
        } catch (\Throwable $e) {
            $sent = false;
        }

        $msg = sprintf(
            'User created: id=%d, emails=%s%s. %s',
            $user->getId(),
            $user->getEmail(),
            $user->getUsername() ? ', username='.$user->getUsername() : '',
            $sent ? 'Email sent.' : 'Email failed to send.'
        );

        // Deliberately NOT printing the password back into the terminal for safety.
        return ['output' => $msg, 'success' => true];
    }

    /**
     * users:update <id|emails> [--emails=...] [--username=...] [--roles=ROLE_X,ROLE_Y]
     * - No password change here.
     * - Email must be valid + unique (if changed).
     * - Roles replace the entire set (normalized to ROLE_*). If omitted, roles unchanged.
     */
    public function updateCommand(array $tokens): array
    {
        // Parse: first positional is the target (id or emails)
        ['args'=>$args, 'flags'=>$f] = ArgsParser::parse($tokens);
        $target   = $args[0] ?? '';
        $newEmail = isset($f['emails']) ? trim((string)$f['emails']) : null;
        $username = isset($f['username']) ? trim((string)$f['username']) : null;
        $rolesStr = $f['roles'] ?? $f['role'] ?? null;

        if ($target === '') {
            return ['output'=>'Usage: users:update <id|emails> [--emails=..] [--username=..] [--roles=ROLE_X,ROLE_Y]', 'success'=>false];
        }

        $user = $this->users->findOneByIdOrEmailInsensitive($target);
        if (!$user) {
            return ['output'=>'User not found (by id/emails).', 'success'=>false];
        }

        $changed = [];

        // Email update (validate + unique)
        if ($newEmail !== null) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return ['output'=>'Invalid emails format.', 'success'=>false];
            }
            $existing = $this->users->findOneBy(['emails' => $newEmail]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return ['output'=>'Email already in use by another account.', 'success'=>false];
            }
            $user->setEmail($newEmail);
            $changed[] = 'emails';
        }

        // Username update
        if ($username !== null) {
            $user->setUsername($username);
            $changed[] = 'username';
        }

        // Roles update (replace entire set if provided)
        if ($rolesStr !== null && $rolesStr !== '') {
            $roles = $this->normalizeRoles((string)$rolesStr);
            if (!$roles) {
                return ['output'=>'No valid roles provided.', 'success'=>false];
            }
            $user->setRoles($roles);
            $changed[] = 'roles';
        }

        if (!$changed) {
            return ['output'=>'Nothing to update. Provide at least one of --emails, --username, --roles.', 'success'=>false];
        }

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            return ['output'=>'Failed to update user: '.$e->getMessage(), 'success'=>false];
        }

        return [
            'output'  => sprintf('Updated user id=%d (%s). Changed: %s',
                $user->getId(),
                $user->getEmail(),
                implode(', ', $changed)
            ),
            'success' => true
        ];
    }
    /**
     * users:forgot-password <id|emails>
     * - Generates a new temporary password, sets it, emails the user.
     * - Terminal does NOT print the password back.
     */
    public function forgotPasswordCommand(array $tokens): array
    {
        ['args'=>$args] = ArgsParser::parse($tokens);
        $target = $args[0] ?? '';
        if ($target === '') {
            return ['output'=>'Usage: users:forgot-password <id|emails>', 'success'=>false];
        }

        $user = $this->users->findOneByIdOrEmailInsensitive($target);
        if (!$user) {
            return ['output'=>'User not found (by id/emails).', 'success'=>false];
        }

        // Generate temporary password (8 hex chars) — adjust length if you prefer
        $temp = bin2hex(random_bytes(4));
        $user->setPassword($this->hasher->hashPassword($user, $temp));

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            return ['output'=>'Failed to reset password: '.$e->getMessage(), 'success'=>false];
        }

        // Send emails (reuse your template or make a dedicated "reset" subject)
        $loginUrl = $this->urls->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMsg = (new TemplatedEmail())
            ->from('no-reply@worktimeflow.local')
            ->to($user->getEmail())
            ->subject('WORKTIMEFLOW — Password reset')
            ->htmlTemplate('admin/emails/password_reset.html.twig')
            ->context([
                'username'   => (string) $user->getUsername(),
                'user_email' => (string) $user->getEmail(),
                'password'   => $temp,
                'login_url'  => $loginUrl,
            ]);

        $sent = false;
        try {
            $this->mailer->send($emailMsg);
            $sent = true;
        } catch (\Throwable $e) {
            // swallow and report
        }

        return [
            'output'  => sprintf('Temporary password set for id=%d (%s). %s',
                $user->getId(),
                $user->getEmail(),
                $sent ? 'Email sent.' : 'Email failed to send.'
            ),
            'success' => true
        ];
    }

    /** Normalize comma-separated roles to ROLE_* and dedup. */
    private function normalizeRoles(string $rolesCsv): array
    {
        $parts = array_map('trim', explode(',', $rolesCsv));
        $norm  = array_map(
            static fn(string $r) => str_starts_with($r = strtoupper($r), 'ROLE_') ? $r : ('ROLE_'.$r),
            $parts
        );
        // Remove empties, dedup
        $norm = array_values(array_unique(array_filter($norm)));
        return $norm;
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
