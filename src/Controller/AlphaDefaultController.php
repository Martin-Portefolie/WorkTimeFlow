<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlphaDefaultController extends AbstractController
{
    #[Route('/alpha/', name: 'app_alpha_default')]
    public function index(): Response
    {
        return $this->render('alpha_default/index.html.twig', [
            'controller_name' => 'AlphaDefaultController',
        ]);
    }
}
