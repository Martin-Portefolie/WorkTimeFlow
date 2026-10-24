<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Admin\UserAdminService;
use App\Service\Admin\UserWelcomeMailException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAdminServiceTest extends TestCase
{
    public function testDuplicateEmailDoesNotPartiallyChangeTheUser(): void
    {
        $existingHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $user = (new User())->setUsername('Original')->setEmail('original@example.test');
        $other = (new User())->setEmail('taken@example.test');
        (new \ReflectionProperty(User::class, 'id'))->setValue($other, 2);
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('findOneByIdOrEmailInsensitive')->with('taken@example.test')->willReturn($other);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $service = new UserAdminService($users, $em, $this->createStub(UserPasswordHasherInterface::class), $this->createStub(MailerInterface::class), $this->createStub(UrlGeneratorInterface::class));
        try {
            $service->update($user, ['username' => 'Changed', 'email' => ' TAKEN@example.test '], new User());
            self::fail('Duplicate email accepted.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('user.email_taken', $e->getMessage());
        }
        self::assertSame('Original', $user->getUsername());
        self::assertSame('original@example.test', $user->getEmail());
    }

    public static function creationAccess(): iterable
    {
        yield 'default' => [[], ['ROLE_USER'], true];
        yield 'inactive administrator' => [['role' => 'ROLE_ADMIN', 'active' => '0'], ['ROLE_USER', 'ROLE_ADMIN'], false];
    }

    #[DataProvider('creationAccess')]
    public function testCreationHashesPasswordAndAppliesAccess(array $access, array $expectedRoles, bool $expectedActive): void
    {
        $password = null;
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByIdOrEmailInsensitive')->willReturn(null);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())->method('hashPassword')->willReturnCallback(function (User $user, string $plain) use (&$password): string {
            $password = $plain;
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $plain);
            return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]);
        });
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())->method('generate')->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)->willReturn('https://example.test/da/login');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function (TemplatedEmail $email) use (&$password): bool {
            self::assertSame('new@example.test', $email->getTo()[0]->getAddress());
            self::assertSame('admin/emails/user_created.html.twig', $email->getHtmlTemplate());
            self::assertSame($password, $email->getContext()['password']);
            self::assertSame('https://example.test/da/login', $email->getContext()['login_url']);
            return true;
        }));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $em->expects(self::once())->method('flush');
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $work) => $work());
        $user = (new UserAdminService($users, $em, $hasher, $mailer, $router))->create([
            'username' => ' New user ', 'email' => ' NEW@example.test ', 'password' => 'ignored-input',
        ] + $access);
        self::assertSame('New user', $user->getUsername());
        self::assertSame('new@example.test', $user->getEmail());
        self::assertTrue(password_verify($password, $user->getPassword()));
        self::assertNotSame('ignored-input', $password);
        self::assertSame($expectedRoles, $user->getRoles());
        self::assertSame($expectedActive, $user->isActive());
    }

    public function testUpdateKeepsExistingPrivilegesPasswordAndTeams(): void
    {
        $existingHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $user = (new User())->setUsername('Original')->setEmail('original@example.test')
            ->setRoles(['ROLE_ADMIN'])->setIsActive(false)->setPassword($existingHash);
        $team = new \App\Entity\Team();
        $user->addTeam($team);
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByIdOrEmailInsensitive')->willReturn($user);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hashPassword');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        (new UserAdminService($users, $em, $hasher, $this->createStub(MailerInterface::class), $this->createStub(UrlGeneratorInterface::class)))->update($user, [
            'username' => ' Changed ', 'email' => ' ORIGINAL@example.test ',
            'password' => 'ignored',
        ], new User());
        self::assertSame('Changed', $user->getUsername());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertFalse($user->isActive());
        self::assertSame($existingHash, $user->getPassword());
        self::assertTrue($user->getTeams()->contains($team));
    }


    public function testMailerFailureEscapesTheTransactionForRollback(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByIdOrEmailInsensitive')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(static function (callable $work): void {
            try {
                $work();
                self::fail('Mail failure must abort the transaction.');
            } catch (UserWelcomeMailException $e) {
                self::assertSame('Mail unavailable', $e->getPrevious()->getMessage());
                throw $e;
            }
        });
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new \RuntimeException('Mail unavailable'));
        $service = new UserAdminService($users, $em, $this->createStub(UserPasswordHasherInterface::class), $mailer, $this->createStub(UrlGeneratorInterface::class));
        $this->expectException(UserWelcomeMailException::class);
        $service->create(['username' => 'Test user', 'email' => 'test@example.test']);
    }

    public function testAdminCanChangeAnotherUsersAccess(): void
    {
        $user = (new User())->setUsername('Member')->setEmail('member@example.test')->setIsActive(false);
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findOneByIdOrEmailInsensitive')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('flush');
        $service = new UserAdminService($repo, $em, $this->createStub(UserPasswordHasherInterface::class), $this->createStub(MailerInterface::class), $this->createStub(UrlGeneratorInterface::class));
        $actor = new User();
        $service->update($user, ['role' => 'ROLE_ADMIN', 'active' => '1'], $actor);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertTrue($user->isActive());
        $service->update($user, ['role' => 'ROLE_USER', 'active' => '0'], $actor);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isActive());
    }

    public function testSelfProtectionRejectsChangesBeforeMutatingAnyFields(): void
    {
        $user = (new User())->setUsername('Admin')->setEmail('admin@example.test')->setRoles(['ROLE_ADMIN']);
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findOneByIdOrEmailInsensitive')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $service = new UserAdminService($repo, $em, $this->createStub(UserPasswordHasherInterface::class), $this->createStub(MailerInterface::class), $this->createStub(UrlGeneratorInterface::class));
        foreach ([['role' => 'ROLE_USER'], ['active' => '0']] as $change) {
            try {
                $service->update($user, ['username' => 'Must not persist'] + $change, $user);
                self::fail('Self lockout was allowed.');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('user.self_access', $e->getMessage());
            }
            self::assertSame('Admin', $user->getUsername());
            self::assertContains('ROLE_ADMIN', $user->getRoles());
            self::assertTrue($user->isActive());
        }
    }

    public static function invalidData(): iterable
    {
        yield ['role', 'ROLE_SUPER_ADMIN'];
        yield ['role', ['ROLE_ADMIN']];
        yield ['active', 'yes'];
        yield ['active', null];
        yield ['username', 'x'];
        yield ['username', str_repeat('a', 256)];
        yield ['email', 'invalid'];
        yield ['email', str_repeat('a', 175) . '@example.test'];
        yield ['email', ['array@example.test']];
    }

    #[DataProvider('invalidData')]
    public function testInvalidCreationDoesNotPersist(string $field, mixed $value): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByIdOrEmailInsensitive')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $service = new UserAdminService($users, $em, $this->createStub(UserPasswordHasherInterface::class), $this->createStub(MailerInterface::class), $this->createStub(UrlGeneratorInterface::class));
        $data = ['username' => 'Test user', 'email' => 'valid@example.test', 'password' => bin2hex(random_bytes(16))];
        $data[$field] = $value;
        $this->expectExceptionMessage('user.invalid.' . $field);
        $service->create($data);
    }
}
