<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\ActivityLogService;

class TaskObserver
{
    public function created(Task $task): void
    {
        ActivityLogService::logCreate($task);
    }

    public function updated(Task $task): void
    {
        $oldValues = $task->getOriginal();
        $newValues = $task->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($task, $oldValues, $newValues);
        }
    }

    public function deleted(Task $task): void
    {
        ActivityLogService::logDelete($task);
    }
}
