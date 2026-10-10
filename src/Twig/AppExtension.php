<?php

namespace App\Twig;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly CompanyRepository $companies,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_company', [$this, 'getCompany']),
            new TwigFunction('company_logo', [$this, 'getCompanyLogo']),
        ];
    }

    public function getCompany(): ?Company
    {
        return $this->companies->find(1);
    }

    public function getCompanyLogo(?Company $company): string
    {
        $logo = $company?->getLogo();
        if ($logo && is_file($this->projectDir . '/public/' . ltrim($logo, '/'))) {
            return $logo;
        }

        return 'images/worktimeflow.svg';
    }
}
