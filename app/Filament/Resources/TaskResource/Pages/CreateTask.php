<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Services\WhatsAppService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['is_self_assigned'] = false;
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Notify assigned employees via WhatsApp
        $task = $this->record;
        $task->load('employees');
        
        $whatsapp = app(WhatsAppService::class);
        
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
                
                $whatsapp->sendMessage($employee->phone_number, $message);
            }
        }
    }
}
