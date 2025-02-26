<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private ?Client $client = null;

    #[ORM\Column]
    private ?bool $Active = null;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
        $this->todos = new ArrayCollection();
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

    public function isActive(): ?bool
    {
        return $this->Active;
    }

    public function setActive(bool $Active): static
    {
        $this->Active = $Active;

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
}
