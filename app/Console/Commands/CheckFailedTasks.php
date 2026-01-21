<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckFailedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:check-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and mark tasks as late or failed based on end_date and proof submission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Get tasks that ended yesterday or earlier and are not completed/cancelled/failed/late
        $tasks = Task::whereIn('status', ['pending', 'in_progress'])
            ->where('end_date', '<', $today)
            ->with('employees')
            ->get();
        
        $lateCount = 0;
        $failedCount = 0;
        
        foreach ($tasks as $task) {
            $allEmployees = $task->employees;
            
            // Check if any employee has submitted proof
            $employeesWithProof = $allEmployees->filter(function ($employee) use ($task) {
                $proofImagesRaw = $employee->pivot->proof_images ?? [];
                // Ensure it's an array (handle JSON string case)
                if (is_string($proofImagesRaw)) {
                    $pivotProof = json_decode($proofImagesRaw, true) ?? [];
                } elseif (is_array($proofImagesRaw)) {
                    $pivotProof = $proofImagesRaw;
                } else {
                    $pivotProof = [];
                }
                $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;
                
                // Check if proof exists
                if (empty($pivotProof) || !is_array($pivotProof) || count($pivotProof) === 0) {
                    return false;
                }
                
                // If proof_uploaded_at exists, check if it's on the same day as task end_date
                if ($proofUploadedAt) {
                    $uploadDate = Carbon::parse($proofUploadedAt)->startOfDay();
                    $taskDate = Carbon::parse($task->end_date)->startOfDay();
                    return $uploadDate->isSameDay($taskDate);
                }
                
                // If no proof_uploaded_at, assume it's not on the same day
                return false;
            });
            
            // If no employees have submitted proof on the end_date, mark as late
            if ($employeesWithProof->count() === 0) {
                // Task is past end_date and no proof submitted on end_date → mark as late
                $task->update(['status' => 'late']);
                $lateCount++;
            } elseif ($employeesWithProof->count() < $allEmployees->count()) {
                // Some employees submitted proof on end_date, but not all → mark as failed
                $task->update(['status' => 'failed']);
                $failedCount++;
            } else {
                // All employees submitted proof on end_date, but status is still pending/in_progress
                // This shouldn't happen, but mark as completed just in case
                if ($task->status !== 'completed') {
                    $task->update(['status' => 'completed']);
                }
            }
        }
        
        $this->info("Checked {$tasks->count()} tasks. Marked {$lateCount} tasks as late, {$failedCount} tasks as failed.");
        
        return Command::SUCCESS;
    }
}
