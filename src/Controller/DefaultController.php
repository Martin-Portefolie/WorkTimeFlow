<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $sections = [
            'admin_section' => ['company', 'client', 'project', 'todo', 'users'],
            'user_profile_section' => ['time-fill', 'todo', 'globe', 'users'],
        ];

        $features = [
            'github' => [
                'icon' => 'images/frontpage/github-mark-white.png',
            ],
            'self_hosting' => [
                'icon' => 'images/frontpage/server.svg',
            ],
            'setup_support' => [
                'icon' => 'images/frontpage/wrenc.svg',
            ],
        ];

        return $this->render('default/index.html.twig', [
            'sections' => $sections,
            'features' => $features, // Ensure features are passed too
        ]);
    }

}
