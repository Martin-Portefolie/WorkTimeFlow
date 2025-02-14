<?php

namespace App\Entity;

use App\Repository\TestAutosaveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestAutosaveRepository::class)]
class TestAutosave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $totalMinutes = null;

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getTotalMinutes(): ?int
    {
        return $this->totalMinutes;
    }

    public function setTotalMinutes(int $minutes): static
    {
        $this->totalMinutes = $minutes;
        return $this;
    }

// Helper methods to convert total minutes to hours and minutes
    public function getHours(): int
    {
        return intdiv($this->totalMinutes, 60);
    }

    public function getMinutes(): int
    {
        return $this->totalMinutes % 60;
    }

// Helper method to set hours and minutes
    public function setHoursAndMinutes(int $hours, int $minutes): static
    {
        $this->totalMinutes = ($hours * 60) + $minutes;
        return $this;
    }

}
