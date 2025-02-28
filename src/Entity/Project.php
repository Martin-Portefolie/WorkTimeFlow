<?php

namespace App\Entity;

use App\Enum\Priority;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Team>
     */
    #[ORM\ManyToMany(targetEntity: Team::class, mappedBy: 'projects')]
    private Collection $teams;

    /**
     * @var Collection<int, Todo>
     */
    #[ORM\OneToMany(targetEntity: Todo::class, mappedBy: 'project')]
    private Collection $todos;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;
    #[ORM\Column(type: 'boolean')]
    private bool $isArchived = false;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(type: 'string', enumType: Priority::class)]
    private Priority $priority;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $estimatedBudget = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedMinutes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $lastUpdated;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->lastUpdated = new \DateTime();
    }

    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->todos = new ArrayCollection();
        $this->lastUpdated = new \DateTime();
        $this->priority = Priority::MEDIUM;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): static
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
            $team->addProject($this);
        }

        return $this;
    }

    public function removeTeam(Team $team): static
    {
        if ($this->teams->removeElement($team)) {
            $team->removeProject($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Todo>
     */
    public function getTodos(): Collection
    {
        return $this->todos;
    }

    public function addTodo(Todo $todo): static
    {
        if (!$this->todos->contains($todo)) {
            $this->todos->add($todo);
            $todo->setProject($this);
        }

        return $this;
    }

    public function removeTodo(Todo $todo): static
    {
        if ($this->todos->removeElement($todo)) {
            // set the owning side to null (unless already changed)
            if ($todo->getProject() === $this) {
                $todo->setProject(null);
            }
        }

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getTotalMinutesUsed(): int
    {
        $totalMinutes = 0;
        foreach ($this->todos as $todo) {
            foreach ($todo->getTimelogs() as $timelog) {
                $totalMinutes += $timelog->getTotalMinutes();
            }
        }

        return $totalMinutes;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(\DateTimeInterface $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getPriority(): ?Priority
    {
        return $this->priority;
    }

    public function setPriority(Priority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getEstimatedBudget(): ?string
    {
        return $this->estimatedBudget;
    }

    public function setEstimatedBudget(?string $estimatedBudget): static
    {
        $this->estimatedBudget = $estimatedBudget;

        return $this;
    }

    public function setEstimatedTime(?int $minutes): static
    {
        $this->estimatedMinutes = $minutes;

        return $this;
    }

    public function getEstimatedMinutes(): ?int
    {
        return $this->estimatedMinutes;
    }

    public function getRemainingMinutes(): ?int
    {
        if (null === $this->estimatedMinutes) {
            return null;
        }

        $usedMinutes = $this->getTotalMinutesUsed();

        return max(0, $this->estimatedMinutes - $usedMinutes);
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeInterface $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        return $this;
    }
}
