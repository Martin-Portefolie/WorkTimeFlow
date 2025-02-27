<?php

namespace App\Entity;

use App\Enum\TodoStatus;
use App\Repository\TodoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateEnd = null;

    #[ORM\ManyToOne(inversedBy: 'todos')]
    private ?Project $project = null;

    /**
     * @var Collection<int, Timelog>
     */
    #[ORM\OneToMany(targetEntity: Timelog::class, mappedBy: 'todo')]
    private Collection $timelogs;

    #[ORM\Column(type: 'string', length: 50, enumType: TodoStatus::class, options: ['default' => 'Pending'])]
    private TodoStatus $status = TodoStatus::PENDING;

    public function __construct()
    {
        $this->timelogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(\DateTimeInterface $dateStart): static
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(\DateTimeInterface $dateEnd): static
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, Timelog>
     */
    public function getTimelogs(): Collection
    {
        return $this->timelogs;
    }

    public function addTimelog(Timelog $timelog): static
    {
        if (!$this->timelogs->contains($timelog)) {
            $this->timelogs->add($timelog);
            $timelog->setTodo($this);
        }

        return $this;
    }

    public function removeTimelog(Timelog $timelog): static
    {
        if ($this->timelogs->removeElement($timelog)) {
            // set the owning side to null (unless already changed)
            if ($timelog->getTodo() === $this) {
                $timelog->setTodo(null);
            }
        }

        return $this;
    }

    public function getStatus(): TodoStatus
    {
        return $this->status;
    }

    public function setStatus(TodoStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
