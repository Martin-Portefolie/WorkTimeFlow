<?php

namespace App\Twig;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_company', [$this, 'getCompany']),
        ];
    }

    public function getCompany(): ?Company
    {
        return $this->entityManager->getRepository(Company::class)->find(1);
    }
}
