<?php
namespace App\Service\Terminal\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Terminal\ArgsParser;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


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
        private Security $security,
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
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        $limit = (isset($args[0]) && ctype_digit($args[0])) ? (int) $args[0] : 50;
        $q     = isset($flags['q']) ? (string) $flags['q'] : null;

        // Map --is-active to a tri-state: true/false/null
        $raw = isset($flags['is-active']) ? strtolower((string)$flags['is-active']) : null;

        $isActive = true; // default: only active
        if ($raw !== null) {
            $truthy  = ['1','true','yes','y','on','active'];
            $falsy   = ['0','false','no','n','off','inactive'];
            $neutral = ['all','*','any'];

            if (in_array($raw, $truthy, true)) {
                $isActive = true;
            } elseif (in_array($raw, $falsy, true)) {
                $isActive = false;
            } elseif (in_array($raw, $neutral, true)) {
                $isActive = null; // no filter
            } else {
                // if someone passes a weird value, keep default (true) but hint in output
            }
        }

        $rows = $this->users->fetchListRowsSearched($limit, $q, $isActive);
        if (!$rows) {
            return ['output' => 'No users found.', 'success' => true];
        }

        $lines = array_map(
            static fn (array $r) => sprintf(
                '%d | %s | %s | %s%s',
                $r['id'],
                $r['email'],
                ($r['username'] ?? ''),
                implode(',', $r['roles']),
                (isset($r['active']) && $r['active'] === false) ? ' (inactive)' : ''
            ),
            $rows
        );

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

        // Flags + aliases
        $email    = isset($f['email']) ? trim((string)$f['email']) : '';
        $username = isset($f['username']) ? trim((string)$f['username']) : null;
        if ($username === null && isset($f['name'])) {
            $username = trim((string)$f['name']); // alias
        }
        $password = isset($f['password']) ? (string)$f['password'] : null;
        $rolesStr = $f['roles'] ?? $f['role'] ?? null;        // accept both

        if ($email === '') {
            return ['output' => 'Usage: users:add --email=alice@example.com [--username=Alice|--name=Alice] [--password=Pass123] [--roles=ROLE_USER,ROLE_ADMIN]', 'success' => false];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['output' => 'Invalid email format.', 'success' => false];
        }
        if ($this->users->findOneBy(['email' => $email])) {
            return ['output' => 'Email already exists.', 'success' => false];
        }

        // Derive username if missing/empty (ensure NOT NULL)
        if ($username === null || $username === '') {
            $local = strstr($email, '@', true) ?: $email;
            $username = $local;
        }

        // Roles
        $roles = ['ROLE_USER'];
        if ($rolesStr !== null && $rolesStr !== '') {
            $roles = $this->normalizeRoles((string)$rolesStr) ?: ['ROLE_USER'];
        }

        // Password
        $plain = $password ?: bin2hex(random_bytes(4)); // 8 hex chars

        // Create
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username); // ← always set
        $user->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, $plain));

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (\Throwable $e) {
            return ['output' => 'Failed to create user: '.$e->getMessage(), 'success' => false];
        }

        // Email
        $loginUrl = $this->urls->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $emailMsg = (new TemplatedEmail())
            ->from('no-reply@worktimeflow.local')
            ->to($user->getEmail())
            ->subject('Your WORKTIMEFLOW account')
            ->htmlTemplate('admin/emails/user_created.html.twig')
            ->context([
                'username'   => (string) $user->getUsername(),
                'user_email' => (string) $user->getEmail(),
                'password'   => $plain,
                'login_url'  => $loginUrl,
            ]);

        $sent = false;
        try { $this->mailer->send($emailMsg); $sent = true; } catch (\Throwable $e) {}

        $msg = sprintf(
            'User created: id=%d, email=%s, username=%s, roles=%s. %s',
            $user->getId(),
            $user->getEmail(),
            $user->getUsername(),
            implode(',', $user->getRoles()),
            $sent ? 'Email sent.' : 'Email failed to send.'
        );

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
            return ['output'=>'Usage: users:update <id|email> [--email=..] [--username=..] [--roles=ROLE_X,ROLE_Y]', 'success'=>false];
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
            $existing = $this->users->findOneBy(['email' => $newEmail]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return ['output'=>'Email already in use by another account.', 'success'=>false];
            }
            $user->setEmail($newEmail);
            $changed[] = 'email';
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

    /**
     * users:delete <id|email> [--force]
     *
     * - Resolves a user by numeric id or case-insensitive email.
     * - Detaches teams (ManyToMany).
     * - Detaches timelogs by setting Timelog.user = null (safe, given your entity API).
     * - If related records exist and --force is NOT provided, returns a warning and does nothing.
     */
    public function deleteCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        $target = $args[0] ?? '';
        if ($target === '') {
            return ['output' => 'Usage: users:delete <id|email> [--force]', 'success' => false];
        }

        $user = $this->users->findOneByIdOrEmailInsensitive($target);
        if (!$user) {
            return ['output' => 'User not found (by id/email).', 'success' => false];
        }

        // 🛡️ Self-protection: don’t allow deleting yourself
        if ($this->security->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            $me = $this->security->getUser();
            if (method_exists($me, 'getId') && $user->getId() === $me->getId()) {
                return ['output' => 'Refusing to delete the currently logged-in account.', 'success' => false];
            }
        }

        // Count related records to warn before destructive action
        $teamsCount   = $user->getTeams()->count();
        $timelogsCount = $user->getTimelogs()->count();

        $hasRelations = ($teamsCount > 0) || ($timelogsCount > 0);
        $force = isset($flags['force']) && $flags['force'] !== false;

        if ($hasRelations && !$force) {
            return [
                'output'  => sprintf(
                    'Refusing to delete. Found %d team link(s) and %d timelog(s). Re-run with --force to detach and delete.',
                    $teamsCount, $timelogsCount
                ),
                'success' => false
            ];
        }

        // Detach teams (inverse side). This removes join-table rows.
        // Because User is the inverse side (inversedBy="users"), we remove links from the owning side as well.
        $detachedTeams = 0;
        foreach ($user->getTeams() as $team) {
            $user->removeTeam($team); // your entity has removeTeam()
            $detachedTeams++;
        }

        // Detach timelogs by nulling the owning side
        $detachedLogs = 0;
        foreach ($user->getTimelogs() as $log) {
            $log->setUser(null); // Timelog::setUser(?User) exists in your code path
            $detachedLogs++;
        }

        // Finally remove the user
        $id    = $user->getId();
        $email = (string) $user->getEmail();

        try {
            $this->em->remove($user);
            $this->em->flush();
        } catch (\Throwable $e) {
            return ['output' => 'Failed to delete user: '.$e->getMessage(), 'success' => false];
        }

        return [
            'output'  => sprintf(
                'Deleted user id=%d (%s). Detached %d team link(s), %d timelog(s).',
                $id, $email, $detachedTeams, $detachedLogs
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

    public function deactivateCommand(array $tokens): array
    {
        ['args'=>$args] = ArgsParser::parse($tokens);
        $target = $args[0] ?? '';
        if ($target === '') {
            return ['output'=>'Usage: users:deactivate <id|email>', 'success'=>false];
        }

        $user = $this->users->findOneByIdOrEmailInsensitive($target);
        if (!$user) return ['output'=>'User not found (by id/email).', 'success'=>false];

        if (method_exists($this->security->getUser(), 'getId')
            && $this->security->getUser()?->getId() === $user->getId()) {
            return ['output'=>'Refusing to deactivate the currently logged-in account.', 'success'=>false];
        }

        $user->setIsActive(false);
        try { $this->em->flush(); }
        catch (\Throwable $e) { return ['output'=>'Failed to deactivate user: '.$e->getMessage(), 'success'=>false]; }

        return ['output'=>sprintf('User id=%d (%s) deactivated.', $user->getId(), $user->getEmail()), 'success'=>true];
    }

    public function activateCommand(array $tokens): array
    {
        ['args'=>$args] = ArgsParser::parse($tokens);
        $target = $args[0] ?? '';
        if ($target === '') {
            return ['output'=>'Usage: users:activate <id|email>', 'success'=>false];
        }

        $user = $this->users->findOneByIdOrEmailInsensitive($target);
        if (!$user) return ['output'=>'User not found (by id/email).', 'success'=>false];

        $user->setIsActive(true);
        try { $this->em->flush(); }
        catch (\Throwable $e) { return ['output'=>'Failed to activate user: '.$e->getMessage(), 'success'=>false]; }

        return ['output'=>sprintf('User id=%d (%s) activated.', $user->getId(), $user->getEmail()), 'success'=>true];
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
