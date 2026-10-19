<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckFailedTasks extends Command
{
    protected $signature = 'tasks:check-failed';

    protected $description = 'Check and mark tasks as late or failed based on end_date and proof submission';

    public function handle(): int
    {
        $today = Carbon::today();

        $tasks = Task::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('end_date', '<', $today)
            ->with('employees')
            ->get();

        $lateCount = 0;
        $failedCount = 0;
        $completedCount = 0;

        foreach ($tasks as $task) {
            if ($task->checkAndUpdateLateStatus()) {
                if ($task->status === 'late') {
                    $lateCount++;
                } elseif ($task->status === 'failed') {
                    $failedCount++;
                }

                continue;
            }

            // Past end_date, still pending/in_progress, all assignees proved on end_date → complete
            if ($task->employees->isNotEmpty() && $this->allEmployeesProvedOnEndDate($task)) {
                $task->update(['status' => 'completed']);
                $completedCount++;
            }
        }

        $this->info("Checked {$tasks->count()} tasks. Late: {$lateCount}, failed: {$failedCount}, completed: {$completedCount}.");

        return self::SUCCESS;
    }

    private function allEmployeesProvedOnEndDate(Task $task): bool
    {
        $endDate = Carbon::parse($task->end_date)->startOfDay();

        return $task->employees->every(function ($employee) use ($endDate) {
            $proofImagesRaw = $employee->pivot->proof_images ?? [];
            if (is_string($proofImagesRaw)) {
                $pivotProof = json_decode($proofImagesRaw, true) ?? [];
            } elseif (is_array($proofImagesRaw)) {
                $pivotProof = $proofImagesRaw;
            } else {
                $pivotProof = [];
            }

            if ($pivotProof === []) {
                return false;
            }

            $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;
            if (! $proofUploadedAt) {
                return false;
            }

            return Carbon::parse($proofUploadedAt)->startOfDay()->isSameDay($endDate);
        });
    }
}
