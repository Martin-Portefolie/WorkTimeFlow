<?php

namespace App\Tests\Controller;

use App\Entity\Todo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SiteSmokeTest extends WebTestCase
{
    public static function publicPages(): iterable
    {
        yield ['/da/'];
        yield ['/en/'];
        yield ['/da/login'];
        yield ['/en/login'];
    }

    #[DataProvider('publicPages')]
    public function testPublicPageRenders(string $path): void
    {
        $client = self::createClient();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
        self::assertSelectorExists('script[type="importmap"]');
        self::assertSelectorExists('link[rel="stylesheet"]');
    }

    public function testAdminCanLogInAndReadTodos(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail('ci-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setUsername('CI Admin')
            ->setRoles(['ROLE_ADMIN']);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'ci-test-password'));
        $todo = (new Todo())->setName('CI smoke todo')
            ->setDateStart(new \DateTime())->setDateEnd(new \DateTime('+1 day'));
        $em->persist($user);
        $em->persist($todo);
        $em->flush();
        $userId = $user->getId();
        $todoId = $todo->getId();

        try {
            $crawler = $client->request('GET', '/en/login');
            $client->submit($crawler->filter('form')->form([
                '_username' => $user->getEmail(),
                '_password' => 'ci-test-password',
            ]));
            self::assertResponseRedirects();
            $client->request('GET', '/en/admin/');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-controller="terminal--terminal"]');

            foreach (['todo.l' => 'todos/list', 'todo.s ' . $todoId => 'todos/show'] as $command => $view) {
                $client->request('POST', '/en/admin/terminal/run', ['input' => $command]);
                self::assertResponseIsSuccessful();
                self::assertSelectorExists('[data-gui-view="' . $view . '"]');
                self::assertStringContainsString('CI smoke todo', $client->getResponse()->getContent());
                self::assertStringNotContainsString('is not viable', $client->getResponse()->getContent());
            }
        } finally {
            $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
            $connection->delete('todo', ['id' => $todoId]);
            $connection->delete('user', ['id' => $userId]);
        }
    }
}
