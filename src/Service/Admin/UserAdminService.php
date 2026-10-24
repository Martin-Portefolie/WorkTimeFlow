<?php

namespace App\Service\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UserAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function list(): array
    {
        return $this->users->fetchListRowsSearched(100, null, null);
    }

    public function update(User $user, array $data, User $actor): void
    {
        [$username, $email] = $this->validateDetails($data, $user);
        [$roles, $active] = $this->validateAccess($data, $user);
        $isSelf = $actor === $user || ($actor->getId() !== null && $actor->getId() === $user->getId());
        if ($isSelf && (!$active || (in_array('ROLE_ADMIN', $user->getRoles(), true) && !in_array('ROLE_ADMIN', $roles, true)))) {
            throw new \InvalidArgumentException('user.self_access');
        }
        $user->setUsername($username)->setEmail($email)->setRoles($roles)->setIsActive($active);
        $this->em->flush();
    }

    public function create(array $data): User
    {
        [$username, $email] = $this->validateDetails($data);
        [$roles, $active] = $this->validateAccess($data);
        $password = bin2hex(random_bytes(16));
        $user = (new User())->setUsername($username)->setEmail($email)->setRoles($roles)->setIsActive($active);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $email = (new TemplatedEmail())
            ->from('noreply@worktimeflow.test')
            ->to($user->getEmail())
            ->subject('Welcome to WORKTIMEFLOW')
            ->htmlTemplate('admin/emails/user_created.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'user_email' => $user->getEmail(),
                'password' => $password,
                'login_url' => $this->router->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->em->wrapInTransaction(function () use ($user, $email): void {
            $this->em->persist($user);
            // Check database constraints before handing the welcome email to the mailer.
            $this->em->flush();
            try {
                $this->mailer->send($email);
            } catch (\Throwable $exception) {
                throw new UserWelcomeMailException('user.mail_failed', 0, $exception);
            }
        });

        return $user;
    }

    private function validateAccess(array $data, ?User $user = null): array
    {
        $roles = $user?->getRoles() ?? ['ROLE_USER'];
        $active = $user?->isActive() ?? true;
        if (array_key_exists('role', $data)) {
            if (!in_array($data['role'], ['ROLE_USER', 'ROLE_ADMIN'], true)) {
                throw new \InvalidArgumentException('user.invalid.role');
            }
            $roles = $data['role'] === 'ROLE_ADMIN' ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'];
        }
        if (array_key_exists('active', $data)) {
            if (!in_array($data['active'], ['0', '1'], true)) {
                throw new \InvalidArgumentException('user.invalid.active');
            }
            $active = $data['active'] === '1';
        }

        return [$roles, $active];
    }

    private function validateDetails(array $data, ?User $user = null): array
    {
        $username = $data['username'] ?? $user?->getUsername();
        $email = $data['email'] ?? $user?->getEmail();
        if (!is_string($username) || mb_strlen(trim($username)) < 2 || mb_strlen(trim($username)) > 255) {
            throw new \InvalidArgumentException('user.invalid.username');
        }
        if (!is_string($email) || mb_strlen(trim($email)) > 180 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('user.invalid.email');
        }
        $email = mb_strtolower(trim($email));
        $existing = $this->users->findOneByIdOrEmailInsensitive($email);
        if ($existing !== null && ($user === null || $existing->getId() !== $user->getId())) {
            throw new \InvalidArgumentException('user.email_taken');
        }

        return [trim($username), $email];
    }
}
