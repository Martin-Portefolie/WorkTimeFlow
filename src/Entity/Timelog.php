<?php

namespace App\Entity;

use App\Repository\TimelogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimelogRepository::class)]
class Timelog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column(type: Types::INTEGER)]
    private ?int $totalMinutes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'timelogs')]
    private ?Todo $todo = null;

    #[ORM\ManyToOne(inversedBy: 'timelogs')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

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


    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTodo(): ?Todo
    {
        return $this->todo;
    }

    public function setTodo(?Todo $todo): static
    {
        $this->todo = $todo;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
