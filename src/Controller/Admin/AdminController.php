<?php

namespace App\Controller\Admin;

use App\Service\Terminal\TerminalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/admin/terminal/run', name: 'admin_terminal_run', methods: ['POST'])]
    public function run(Request $request, TerminalService $terminal): Response
    {
        $input  = (string) $request->request->get('input', '');
        $result = $terminal->execute($input);

        $output = $result['output'] ?? null;
        if (!$output && isset($result['view'])) {
            $output = $this->renderView($result['view'], $result['vars'] ?? []);
        }

        return $this->render('partials/_line.html.twig', [
            'input'   => $input,
            'output'  => $output ?? '',
            'success' => (bool)($result['success'] ?? false),
            'user'    => $this->getUser()?->getUserIdentifier() ?? 'guest',
        ]);
    }

}
