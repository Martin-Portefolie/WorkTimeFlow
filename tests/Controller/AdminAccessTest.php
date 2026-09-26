<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminAccessTest extends WebTestCase
{
    public function testAnonymousUserCannotExecuteAdminCommands(): void
    {
        $client = self::createClient();
        $client->request('POST', '/en/admin/terminal/run', ['input' => 'todo.l']);

        self::assertResponseRedirects('/en/login');
    }
}
