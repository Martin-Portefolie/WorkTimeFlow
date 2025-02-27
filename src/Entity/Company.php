<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: "json")]
    private array $rates = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getRates(): array
    {
        if (is_string($this->rates)) {
            return json_decode($this->rates, true) ?? [];
        }
        return is_array($this->rates) ? $this->rates : [];
    }

    public function setRates(array $rates): static
    {
        $formattedRates = [];
        foreach ($rates as $rate) {
            if (!empty($rate['key']) && !empty($rate['value'])) {
                $formattedRates[$rate['key']] = $rate['value'];
            }
        }

        $this->rates = $formattedRates; // Save as proper key-value JSON
        return $this;
    }
}
