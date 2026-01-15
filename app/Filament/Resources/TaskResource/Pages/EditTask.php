<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Services\WhatsAppService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;
    
    protected array $originalEmployeeIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeFill(): void
    {
        // Store original employee IDs before form is filled
        $this->record->load('employees');
        $this->originalEmployeeIds = $this->record->employees->pluck('id')->toArray();
    }

    protected function afterSave(): void
    {
        // Check if employees were changed
        $task = $this->record;
        $task->load('employees');
        $currentEmployeeIds = $task->employees->pluck('id')->toArray();
        
        // Get newly assigned employees
        $originalEmployeeIds = $this->originalEmployeeIds ?? [];
        $newEmployeeIds = array_diff($currentEmployeeIds, $originalEmployeeIds);
        
        if (!empty($newEmployeeIds)) {
            $whatsapp = app(WhatsAppService::class);
            $newEmployees = $task->employees->whereIn('id', $newEmployeeIds);
            
            foreach ($newEmployees as $employee) {
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
                    
                    $whatsapp->sendMessage($employee->phone_number, $message);
                }
            }
        }
    }
}
