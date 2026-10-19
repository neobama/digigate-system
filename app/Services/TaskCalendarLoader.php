<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaskCalendarLoader
{
    /**
     * Load tasks overlapping a calendar month. Read-only — late/failed status
     * is updated by the scheduled `tasks:check-failed` command, not on UI mount.
     *
     * @param  callable(Builder): void|null  $constrain
     * @return Collection<int, Task>
     */
    public static function forMonth(int $year, int $month, ?callable $constrain = null): Collection
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Task::query()
            ->with(['employees' => function ($query) {
                $query->withPivot('proof_images', 'notes', 'proof_uploaded_at');
            }])
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                        $q->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });

        if ($constrain) {
            $constrain($query);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  list<string>  $extraKeys  Additional model attributes to include (e.g. proof_images, notes)
     * @return list<array<string, mixed>>
     */
    public static function toCalendarRows(Collection $tasks, array $extraKeys = []): array
    {
        return $tasks->map(function (Task $task) use ($extraKeys) {
            $row = [
                'id' => $task->id,
                'title' => $task->title,
                'start' => $task->start_date->format('Y-m-d'),
                'end' => $task->end_date->format('Y-m-d'),
                'status' => $task->status,
                'employees' => $task->employees->pluck('name')->toArray(),
                'description' => $task->description,
            ];

            foreach ($extraKeys as $key) {
                $value = $task->{$key};
                $row[$key] = $key === 'proof_images' ? ($value ?? []) : $value;
            }

            return $row;
        })->values()->all();
    }

    /**
     * @param  array<int, array<int, array{row?: int}>>  $tasksByDay
     */
    public static function maxRowsFromTasksByDay(array $tasksByDay): int
    {
        $maxRow = 0;

        foreach ($tasksByDay as $dayTasks) {
            foreach ($dayTasks as $taskInfo) {
                if (isset($taskInfo['row']) && $taskInfo['row'] > $maxRow) {
                    $maxRow = $taskInfo['row'];
                }
            }
        }

        return $maxRow + 1;
    }
}
