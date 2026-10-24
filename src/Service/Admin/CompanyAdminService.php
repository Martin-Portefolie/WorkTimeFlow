<?php

namespace App\Service\Admin;

use App\Entity\Company;
use App\Entity\Rate;
use App\Repository\CompanyRepository;
use App\Repository\RateRepository;
use App\Service\CurrencyCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CompanyAdminService
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly RateRepository $rates,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CurrencyCatalog $currencies,
    ) {
    }

    public function getCompany(): ?Company
    {
        return $this->companies->find(1);
    }

    public function updateCurrency(string $currency, ?string $region = null): Company
    {
        if (!$this->currencies->contains($currency, $region)) {
            throw new \InvalidArgumentException('company.currency_invalid');
        }
        $company = $this->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('company.rates.company_required');
        }
        $company->setCurrency($currency);
        $this->entityManager->flush();
        return $company;
    }

    public function uploadLogo(UploadedFile $file): Company
    {
        $errors = $this->validator->validate($file, new Image(
            maxSize: '2M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        ));
        if (count($errors)) {
            throw new \InvalidArgumentException((string) $errors[0]->getMessage());
        }

        $company = $this->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('Save the company name before uploading a logo.');
        }

        $directory = $this->projectDir . '/public/uploads/company';
        $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
        $oldLogo = $company->getLogo();
        $file->move($directory, $filename);
        $company->setLogo('uploads/company/' . $filename);
        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $company->setLogo($oldLogo);
            unlink($directory . '/' . $filename);
            throw $exception;
        }

        // Only delete uploads managed here, after the new logo has been saved.
        if ($oldLogo && preg_match('#^uploads/company/[a-f0-9]{32}\.(png|jpe?g|webp)$#', $oldLogo)) {
            $oldPath = $this->projectDir . '/public/' . $oldLogo;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        return $company;
    }

    public function updateName(string $name): Company
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Company name is required.');
        }

        $company = $this->getCompany() ?? (new Company());
        $company->setName($name);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $company;
    }

    /** @return Rate[] */
    public function getRates(): array
    {
        $company = $this->getCompany();
        return $company ? $this->rates->findBy(['company' => $company], ['name' => 'ASC', 'id' => 'ASC']) : [];
    }

    public function createRate(string $name, string $value): Rate
    {
        $company = $this->getCompany();
        if (!$company) {
            throw new \InvalidArgumentException('company.rates.company_required');
        }

        [$name, $value] = $this->validateRate($name, $value);

        $rate = (new Rate())->setName($name)->setValue($value);
        $rate->setCompany($company);
        $this->entityManager->persist($rate);
        $this->entityManager->flush();

        return $rate;
    }

    public function deleteRate(int $id): void
    {
        $rate = $this->rates->find($id);
        if (!$rate || $rate->getCompany() !== $this->getCompany()) {
            throw new \InvalidArgumentException('company.rates.not_found');
        }

        if ($this->rates->isUsedByProject($rate)) {
            throw new \InvalidArgumentException('company.rates.in_use');
        }

        $this->entityManager->remove($rate);
        $this->entityManager->flush();
    }

    public function updateRate(int $id, string $name, string $value): Rate
    {
        $rate = $this->rates->find($id);
        if (!$rate || $rate->getCompany() !== $this->getCompany()) {
            throw new \InvalidArgumentException('company.rates.not_found');
        }

        [$name, $value] = $this->validateRate($name, $value);

        $rate->setName($name)->setValue($value);
        $this->entityManager->flush();

        return $rate;
    }
    /** @return array{string, string} */
    private function validateRate(string $name, string $value): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('company.rates.invalid_name');
        }
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            throw new \InvalidArgumentException('company.rates.invalid_value');
        }
        return [$name, (ltrim($parts[1], '0') ?: '0') . '.' . str_pad($parts[2] ?? '', 2, '0')];
    }

}
