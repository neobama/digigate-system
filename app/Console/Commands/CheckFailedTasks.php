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
    protected $description = 'Check and mark tasks as failed if no proof submitted on the same day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Get tasks that ended yesterday or earlier and are not completed/cancelled/failed
        $tasks = Task::whereIn('status', ['pending', 'in_progress'])
            ->where('end_date', '<', $today)
            ->with('employees')
            ->get();
        
        $failedCount = 0;
        
        foreach ($tasks as $task) {
            $allEmployees = $task->employees;
            $employeesWithProof = $allEmployees->filter(function ($employee) use ($task) {
                $pivotProof = $employee->pivot->proof_images ?? [];
                $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;
                
                // Check if proof exists and was uploaded on the same day as task
                if (empty($pivotProof) || !is_array($pivotProof) || count($pivotProof) === 0) {
                    return false;
                }
                
                // If proof_uploaded_at exists, check if it's on the same day as task
                if ($proofUploadedAt) {
                    $uploadDate = Carbon::parse($proofUploadedAt)->startOfDay();
                    $taskDate = Carbon::parse($task->end_date)->startOfDay();
                    return $uploadDate->isSameDay($taskDate);
                }
                
                // If no proof_uploaded_at, assume it's not on the same day (fail)
                return false;
            });
            
            // If not all employees submitted proof on the same day, mark as failed
            if ($employeesWithProof->count() < $allEmployees->count()) {
                $task->update(['status' => 'failed']);
                $failedCount++;
            }
        }
        
        $this->info("Checked {$tasks->count()} tasks. Marked {$failedCount} tasks as failed.");
        
        return Command::SUCCESS;
    }
}
