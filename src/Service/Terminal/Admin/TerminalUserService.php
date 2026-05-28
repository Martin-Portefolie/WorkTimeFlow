<?php

namespace App\Service\Terminal\Admin;

use App\Repository\UserRepository;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TerminalUserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TerminalStateService $state,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
    ) {
    }

    // -----------------------------------------------------
    // Show User
    // -----------------------------------------------------

    public function show(array $args = []): array
    {
        // -----------------------------------------------------
        // Resolve identifier
        // -----------------------------------------------------

        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Missing user identifier',
                    'message' => 'Usage: users:show <id|email|username>',
                ],
            ];
        }

        // -----------------------------------------------------
        // Find user
        // -----------------------------------------------------

        $user = $this->userRepository->findOneByIdEmailOrUsername($identifier);

        if (!$user) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'User not found',
                    'message' => sprintf('No user found for "%s".', $identifier),
                ],
            ];
        }

        // -----------------------------------------------------
        // Return terminal payload
        // -----------------------------------------------------

        return [
            'success' => true,
            'view' => 'terminals/admin/terminal_commands/users.html.twig',
            'vars' => [
                'view' => 'show',
                'resource' => 'users',
                'title' => 'User',
                'fields' => [
                    ['label' => 'ID', 'value' => $user->getId()],
                    ['label' => 'Email', 'value' => $user->getEmail()],
                    ['label' => 'Username', 'value' => $user->getUsername()],
                    ['label' => 'Roles', 'value' => implode(', ', $user->getRoles())],
                    ['label' => 'Active', 'value' => $user->isActive() ? 'yes' : 'no'],
                ],
            ],
        ];
    }


    // -----------------------------------------------------
    // List Users
    // -----------------------------------------------------

    public function list(array $args = []): array
    {

        // -----------------------------------------------------
        // Search query
        // -----------------------------------------------------

        $q = $args['q'] ?? null;

        // -----------------------------------------------------
        // Limit
        // -----------------------------------------------------

        $limit = isset($args['limit'])
            ? (int) $args['limit']
            : 25;

        // -----------------------------------------------------
        // Active filter
        // -----------------------------------------------------

        $isActive = true;

        if (isset($args['inactive'])) {
            $isActive = false;
        }

        if (isset($args['all'])) {
            $isActive = null;
        }

        // -----------------------------------------------------
        // Fetch rows
        // -----------------------------------------------------


        $rows = $this->userRepository->fetchListRowsSearched(
            $limit,
            $q,
            $isActive,
        );

        // -----------------------------------------------------
        // Return terminal payload
        // -----------------------------------------------------

        return [
            'success' => true,

            'view' => 'terminals/admin/terminal_commands/users.html.twig',

            'vars' => [
                'view' => 'list',

                'resource' => 'users',

                'title' => 'Users',

                'description' => 'System users available to admin commands.',

                'columns' => [
                    [
                        'key' => 'id',
                        'label' => 'ID',
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                    ],
                    [
                        'key' => 'username',
                        'label' => 'Username',
                    ],
                    [
                        'key' => 'roles',
                        'label' => 'Roles',
                    ],
                    [
                        'key' => 'active',
                        'label' => 'Active',
                    ],
                ],

                'rows' => $rows,
            ],
        ];
    }

    // -----------------------------------------------------
    // Add User
    // -----------------------------------------------------

    public function add(array $args = []): array
    {
        $userId = $this->currentUserId();

        $this->state->set($userId, [
            'mode' => 'users:add',
            'step' => 1,
            'data' => [],
        ]);

        return $this->prompt(
            step: 1,
            total: 4,
            label: 'Enter user email',
            example: 'test@example.com',
        );
    }

    // -----------------------------------------------------
// Activate User
// -----------------------------------------------------

    public function activate(array $args = []): array
    {
        return $this->toggleActive($args, true);
    }

// -----------------------------------------------------
// Deactivate User
// -----------------------------------------------------

    public function deactivate(array $args = []): array
    {
        return $this->toggleActive($args, false);
    }

    // -----------------------------------------------------
    // Update User
    // -----------------------------------------------------

    public function update(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Missing user identifier',
                    'message' => 'Usage: users:update <id|email|username> --email=<email> --username=<name> --roles=ROLE_USER,ROLE_ADMIN',
                ],
            ];
        }

        $user = $this->userRepository->findOneByIdEmailOrUsername($identifier);

        if (!$user) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'User not found',
                    'message' => sprintf('No user found for "%s".', $identifier),
                ],
            ];
        }

        $changed = false;

        if (isset($flags['email'])) {
            if (!filter_var($flags['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'view' => 'terminals/admin/terminal_commands/users.html.twig',
                    'vars' => [
                        'view' => 'error',
                        'title' => 'Invalid email',
                        'message' => sprintf('"%s" is not a valid email address.', $flags['email']),
                    ],
                ];
            }

            $user->setEmail(mb_strtolower(trim((string) $flags['email'])));
            $changed = true;
        }

        if (isset($flags['username'])) {
            $username = trim((string) $flags['username']);

            if (mb_strlen($username) < 2) {
                return [
                    'success' => false,
                    'view' => 'terminals/admin/terminal_commands/users.html.twig',
                    'vars' => [
                        'view' => 'error',
                        'title' => 'Invalid username',
                        'message' => 'Username must be at least 2 characters.',
                    ],
                ];
            }

            $user->setUsername($username);
            $changed = true;
        }

        if (isset($flags['roles'])) {
            $roles = array_filter(array_map(
                'trim',
                explode(',', (string) $flags['roles'])
            ));

            try {
                $roles = $this->validateRoles($roles);
            } catch (\InvalidArgumentException $e) {
                return [
                    'success' => false,
                    'view' => 'terminals/admin/terminal_commands/users.html.twig',
                    'vars' => [
                        'view' => 'error',
                        'title' => 'Invalid roles',
                        'message' => $e->getMessage(),
                    ],
                ];
            }

            $user->setRoles($roles);
            $changed = true;
        }

        if (!$changed) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Nothing to update',
                    'message' => 'Use one or more flags: --email=<email> --username=<name> --roles=ROLE_USER,ROLE_ADMIN',
                ],
            ];
        }

        $this->em->flush();

        return [
            'success' => true,
            'view' => 'terminals/admin/terminal_commands/users.html.twig',
            'vars' => [
                'view' => 'show',
                'title' => 'User updated',
                'fields' => [
                    ['label' => 'ID', 'value' => $user->getId()],
                    ['label' => 'Email', 'value' => $user->getEmail()],
                    ['label' => 'Username', 'value' => $user->getUsername()],
                    ['label' => 'Roles', 'value' => implode(', ', $user->getRoles())],
                    ['label' => 'Active', 'value' => $user->isActive() ? 'yes' : 'no'],
                ],
            ],
        ];
    }

    // -----------------------------------------------------
    // Delete User
    // -----------------------------------------------------

    public function delete(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Missing user identifier',
                    'message' => 'Usage: users:delete <id|email|username> --force',
                ],
            ];
        }

        if (!isset($flags['force'])) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Delete requires confirmation',
                    'message' => sprintf('Run: users:delete %s --force', $identifier),
                ],
            ];
        }

        $user = $this->userRepository->findOneByIdEmailOrUsername($identifier);

        if (!$user) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'User not found',
                    'message' => sprintf('No user found for "%s".', $identifier),
                ],
            ];
        }

        $email = $user->getEmail();

        $this->em->remove($user);
        $this->em->flush();

        return [
            'success' => true,
            'view' => 'terminals/admin/terminal_commands/users.html.twig',
            'vars' => [
                'view' => 'delete',
                'title' => 'User deleted',
                'message' => sprintf('User "%s" was deleted.', $email),
            ],
        ];
    }

    // -----------------------------------------------------
// Forgot password
// -----------------------------------------------------

    public function forgotPassword(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Missing user identifier',
                    'message' => 'Usage: users:forgot-password <id|email|username>',
                ],
            ];
        }

        $user = $this->userRepository->findOneByIdEmailOrUsername($identifier);

        if (!$user) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'User not found',
                    'message' => sprintf('No user found for "%s".', $identifier),
                ],
            ];
        }

        // -----------------------------------------------------
        // Generate temporary password
        // -----------------------------------------------------

        $plainPassword = bin2hex(random_bytes(4));

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plainPassword,
        );

        $user->setPassword($hashedPassword);

        $this->em->flush();

        // -----------------------------------------------------
        // Send reset email
        // -----------------------------------------------------

        $email = (new TemplatedEmail())
            ->from('noreply@worktimeflow.test')
            ->to($user->getEmail())
            ->subject('WORKTIMEFLOW Password Reset')
            ->htmlTemplate('admin/emails/password_reset.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'user_email' => $user->getEmail(),
                'password' => $plainPassword,
                'login_url' => $this->router->generate(
                    'app_login',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Mail delivery failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }

        return [
            'success' => true,
            'view' => 'terminals/admin/terminal_commands/users.html.twig',
            'vars' => [
                'view' => 'show',
                'title' => 'Password reset email sent',
                'fields' => [
                    ['label' => 'User', 'value' => $user->getUsername()],
                    ['label' => 'Email', 'value' => $user->getEmail()],
                ],
            ],
        ];
    }

    // =========================================================
    // Handle interactive user wizard input
    // =========================================================

    public function handleInteractive(string $raw): ?array
    {
        $userId = $this->currentUserId();
        $state = $this->state->get($userId);

        if (!$state || ($state['mode'] ?? null) !== 'users:add') {
            return null;
        }

        $raw = trim($raw);
        $lower = mb_strtolower($raw);

        if ($lower === 'cancel') {
            $this->state->clear($userId);

            return [
                'success' => false,
                'output' => 'User creation cancelled.',
                'await' => false,
            ];
        }

        // If a new users:add command is entered while a stale add wizard exists,
        // restart the wizard instead of treating "u.a" as email input.
        if (in_array($lower, ['u.a', 'users:add'], true)) {
            $this->state->clear($userId);

            return $this->add();
        }

        return $this->handleAddWizard($userId, $state, $raw);
    }

    private function prompt(
        int $step,
        int $total,
        string $label,
        ?string $example = null,
        ?string $error = null,
    ): array {
        return [
            'success' => $error === null,
            'await' => true,
            'view' => 'terminals/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars' => [
                'step' => $step,
                'total' => $total,
                'label' => $label,
                'example' => $example,
                'error' => $error,
            ],
        ];
    }

    private function currentUserId(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'guest';
    }

    // -----------------------------------------------------
    // Handle add wizard
    // -----------------------------------------------------

    private function handleAddWizard(
        string $userId,
        array $state,
        string $input,
    ): array {
        $step = (int) ($state['step'] ?? 1);
        $data = $state['data'] ?? [];

        // -----------------------------------------------------
        // Step 1 — Email
        // -----------------------------------------------------

        if ($step === 1) {
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return $this->prompt(
                    step: 1,
                    total: 4,
                    label: 'Enter user email',
                    example: 'test@example.com',
                    error: 'Invalid email address.',
                );
            }

            $data['email'] = mb_strtolower(trim($input));

            $this->state->set($userId, [
                'mode' => 'users:add',
                'step' => 2,
                'data' => $data,
            ]);

            return $this->prompt(
                step: 2,
                total: 4,
                label: 'Enter username',
                example: 'Martin',
            );
        }

        // -----------------------------------------------------
        // Step 2 — Username
        // -----------------------------------------------------

        if ($step === 2) {
            if (mb_strlen(trim($input)) < 2) {
                return $this->prompt(
                    step: 2,
                    total: 4,
                    label: 'Enter username',
                    example: 'Martin',
                    error: 'Username must be at least 2 characters.',
                );
            }

            $data['username'] = trim($input);

            $this->state->set($userId, [
                'mode' => 'users:add',
                'step' => 3,
                'data' => $data,
            ]);

            return $this->prompt(
                step: 3,
                total: 4,
                label: 'Enter roles (comma separated). Leave empty for ROLE_USER',
                example: 'ROLE_USER,ROLE_ADMIN',
            );
        }

        // -----------------------------------------------------
        // Step 3 — Roles
        // -----------------------------------------------------

        if ($step === 3) {
            $roles = array_filter(array_map(
                'trim',
                explode(',', $input)
            ));

            try {
                $roles = $this->validateRoles($roles);
            } catch (\InvalidArgumentException $e) {
                return $this->prompt(
                    step: 3,
                    total: 4,
                    label: 'Enter roles (comma separated). Leave empty for ROLE_USER',
                    example: 'ROLE_USER,ROLE_ADMIN',
                    error: $e->getMessage(),
                );
            }

            $data['roles'] = $roles;

            $this->state->set($userId, [
                'mode' => 'users:add',
                'step' => 4,
                'data' => $data,
            ]);

            return [
                'success' => true,
                'await' => true,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'show',
                    'title' => 'Confirm user creation',
                    'description' => 'Type "yes" or "y" to confirm. Any other input aborts.',
                    'fields' => [
                        ['label' => 'Email', 'value' => $data['email']],
                        ['label' => 'Username', 'value' => $data['username']],
                        ['label' => 'Roles', 'value' => implode(', ', $roles)],
                    ],
                ],
            ];
        }

        // -----------------------------------------------------
        // Step 4 — Confirm + Persist
        // -----------------------------------------------------

        if ($step === 4) {
            if (!in_array(mb_strtolower($input), ['yes', 'y'], true)) {
                $this->state->clear($userId);

                return [
                    'success' => false,
                    'output' => 'User creation aborted.',
                ];
            }

            $user = new User();

            $user->setEmail($data['email']);
            $user->setUsername($data['username']);
            $user->setRoles($data['roles']);
            $user->setIsActive(true);

            $plainPassword = bin2hex(random_bytes(4));

            $hashed = $this->passwordHasher->hashPassword(
                $user,
                $plainPassword,
            );

            $user->setPassword($hashed);

            $this->em->persist($user);
            $this->em->flush();

            $email = (new TemplatedEmail())
                ->from('noreply@worktimeflow.test')
                ->to($user->getEmail())
                ->subject('Welcome to WORKTIMEFLOW')
                ->htmlTemplate('admin/emails/user_created.html.twig')
                ->context([
                    'username' => $user->getUsername(),
                    'user_email' => $user->getEmail(),
                    'password' => $plainPassword,
                    'login_url' => $this->router->generate(
                        'app_login',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                ]);

            $this->mailer->send($email);

            $this->state->clear($userId);

            return [
                'success' => true,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'show',
                    'title' => 'User created',
                    'description' => 'Welcome email sent successfully.',
                    'fields' => [
                        ['label' => 'ID', 'value' => $user->getId()],
                        ['label' => 'Email', 'value' => $user->getEmail()],
                        ['label' => 'Username', 'value' => $user->getUsername()],
                        ['label' => 'Roles', 'value' => implode(', ', $user->getRoles())],
                        ['label' => 'Active', 'value' => 'yes'],
                    ],
                ],
            ];
        }

        $this->state->clear($userId);

        return [
            'success' => false,
            'output' => 'Wizard state became invalid.',
        ];
    }

    // -----------------------------------------------------
// Validate roles
// -----------------------------------------------------

    private function validateRoles(array $roles): array
    {
        $allowedRoles = [
            'ROLE_USER',
            'ROLE_ADMIN',
        ];

        $roles = array_values(array_unique(array_filter($roles)));
        $invalidRoles = array_diff($roles, $allowedRoles);

        if (!empty($invalidRoles)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid role(s): %s. Allowed roles: ROLE_USER, ROLE_ADMIN.',
                implode(', ', $invalidRoles),
            ));
        }

        return $roles ?: ['ROLE_USER'];
    }

    // -----------------------------------------------------
// Toggle user active state
// -----------------------------------------------------

    private function toggleActive(array $args, bool $active): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'Missing user identifier',
                    'message' => sprintf(
                        'Usage: users:%s <id|email|username>',
                        $active ? 'activate' : 'deactivate',
                    ),
                ],
            ];
        }

        $user = $this->userRepository->findOneByIdEmailOrUsername($identifier);

        if (!$user) {
            return [
                'success' => false,
                'view' => 'terminals/admin/terminal_commands/users.html.twig',
                'vars' => [
                    'view' => 'error',
                    'title' => 'User not found',
                    'message' => sprintf(
                        'No user found for "%s".',
                        $identifier,
                    ),
                ],
            ];
        }

        $user->setIsActive($active);

        $this->em->flush();

        return [
            'success' => true,
            'view' => 'terminals/admin/terminal_commands/users.html.twig',
            'vars' => [
                'view' => 'show',
                'title' => sprintf(
                    'User %s',
                    $active ? 'activated' : 'deactivated',
                ),
                'fields' => [
                    ['label' => 'ID', 'value' => $user->getId()],
                    ['label' => 'Email', 'value' => $user->getEmail()],
                    ['label' => 'Username', 'value' => $user->getUsername()],
                    ['label' => 'Roles', 'value' => implode(', ', $user->getRoles())],
                    ['label' => 'Active', 'value' => $user->isActive() ? 'yes' : 'no'],
                ],
            ],
        ];
    }

}
