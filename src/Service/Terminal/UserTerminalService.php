<?php
namespace App\Service\Terminal;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
     * users:add --email=... [--username=...] [--password=...] [--roles=ROLE_USER,ROLE_ADMIN]
     *
     * - Generates a random password if --password is omitted (8 hex chars).
     * - Default roles to ROLE_USER if --roles omitted.
     * - Sends credentials email using templates/emails/userinfo.html.twig.
     */
    public function addCommand(array $tokens): array
    {
        ['args'=>$args, 'flags'=>$f] = ArgsParser::parse($tokens);

        $email    = isset($f['email']) ? trim((string)$f['email']) : '';
        $username = isset($f['username']) ? trim((string)$f['username']) : '';
        $password = isset($f['password']) ? (string)$f['password'] : null;
        $rolesStr = isset($f['roles']) ? (string)$f['roles'] : null;

        // Basic validation
        if ($email === '') {
            return ['output' => 'Usage: users:add --email=alice@example.com [--username=Alice] [--password=Pass123] [--roles=ROLE_USER,ROLE_ADMIN]', 'success' => false];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['output' => 'Invalid email format.', 'success' => false];
        }

        // Ensure email is unique
        if ($this->users->findOneBy(['email' => $email])) {
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

        // Prepare & send email (reuse your existing template)
        $loginUrl = $this->urls->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMsg = (new TemplatedEmail())
            ->from('no-reply@worktimeflow.local') // or your real domain
            ->to($user->getEmail())
            ->subject('Your WORKTIMEFLOW account')
            ->htmlTemplate('emails/userinfo.html.twig')
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
            'User created: id=%d, email=%s%s. %s',
            $user->getId(),
            $user->getEmail(),
            $user->getUsername() ? ', username='.$user->getUsername() : '',
            $sent ? 'Email sent.' : 'Email failed to send.'
        );

        // Deliberately NOT printing the password back into the terminal for safety.
        return ['output' => $msg, 'success' => true];
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
