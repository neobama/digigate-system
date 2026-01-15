<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\WhatsAppService;

class TaskObserver
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Employees are synced after creation, so we'll notify in saved event
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Check if employees were synced (assigned)
        // This will be handled in CreateTask/EditTask pages after sync
    }

    /**
     * Handle the Task "saved" event.
     */
    public function saved(Task $task): void
    {
        // Notify employees after task is saved (employees are synced via Filament form)
        // We'll use a flag to avoid duplicate notifications
        if (!$task->wasRecentlyCreated && !$task->getAttribute('_notified_employees')) {
            $this->notifyAssignedEmployees($task);
            $task->setAttribute('_notified_employees', true);
        }
    }

    /**
     * Notify assigned employees about the task
     */
    protected function notifyAssignedEmployees(Task $task): void
    {
        $task->load('employees');
        
        foreach ($task->employees as $employee) {
            if (!empty($employee->phone_number)) {
                $message = "📋 *Task Baru Ditetapkan*\n\n";
                $message .= "Judul: {$task->title}\n";
                
                if ($task->description) {
                    $message .= "Deskripsi: {$task->description}\n";
                }
                
                $message .= "Tanggal: " . $task->start_date->format('d/m/Y');
                
                if ($task->start_date->format('Y-m-d') !== $task->end_date->format('Y-m-d')) {
                    $message .= " - " . $task->end_date->format('d/m/Y');
                }
                
                $message .= "\nStatus: " . ucfirst($task->status);
                
                $this->whatsapp->sendMessage($employee->phone_number, $message);
            }
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        //
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        //
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        //
    }
}
