<?php

namespace App\Tests\Service;

use App\Service\Terminal\Admin\TerminalTodoService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TerminalTodoServiceTest extends KernelTestCase
{
    public function testMissingIdentifierReturnsRenderableError(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $result = $container->get(TerminalTodoService::class)->show();

        self::assertFalse($result['success']);
        $html = $container->get('twig')->render($result['view'], $result['vars']);
        self::assertStringContainsString('Missing todo identifier', $html);
        self::assertStringContainsString('todos:show', $html);
    }
}
