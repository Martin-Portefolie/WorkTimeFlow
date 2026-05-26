<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Todo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserProjectService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get teams the user belongs to.
     */
    public function getUserTeams(User $user): array
    {
        return $user->getTeams()->toArray();
    }

    /**
     * Fetch projects for user.
     */
    public function getProjectsByUser(User $user): array
    {
        return $this->entityManager->getRepository(Project::class)->findProjectsByUser($user);
    }

    /**
     * Maps teams to their assigned projects.
     */
    public function mapTeamsToProjects(array $teams, array $projects): array
    {
        $teamProjects = [];

        foreach ($teams as $team) {
            $teamProjects[$team->getName()] = [];
            foreach ($projects as $project) {
                if ($project->getTeams()->contains($team)) {
                    $teamProjects[$team->getName()][] = [
                        'id' => $project->getId(),
                        'name' => $project->getName(),
                    ];
                }
            }
        }

        return $teamProjects;
    }

    /**
     * Fetch todos linked to user’s projects.
     */
    public function getTodosByUserProjects(User $user, array $projects): array
    {
        return $this->entityManager->getRepository(Todo::class)->findTodosByUserProjects($user, $projects);
    }
}
