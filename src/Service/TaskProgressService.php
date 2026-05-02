<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;

class TaskProgressService
{
    /**
     * @param iterable<Task> $tasks
     */
    public function calculate(iterable $tasks): float
    {
        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($tasks as $task) {
            $weight = max(1, (int) $task->getWeight());
            $totalWeight += $weight;

            if ($task->isCompleted()) {
                $completedWeight += $weight;
            }
        }

        if ($totalWeight === 0) {
            return 0.0;
        }

        return round(($completedWeight / $totalWeight) * 100, 2);
    }

    public function syncProject(Project $project): void
    {
        $project->setAvancementProj($this->calculate($project->getTasks()));
    }
}
