<?php

namespace App\Filament\Employee\Pages;

use App\Models\Task;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MyTasks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static string $view = 'filament.employee.pages.my-tasks';
    protected static ?string $navigationLabel = 'Kalender Pekerjaan';
    protected static ?string $title = 'Kalender Pekerjaan Saya';

    public $currentMonth;
    public $currentYear;
    public $tasks = [];
    public $selectedTask = null;
    public $showUploadModal = false;
    public $showCreateModal = false;
    public $proofImages = [];
    public $notes = '';
    
    // Form untuk create task
    public $newTaskTitle = '';
    public $newTaskDescription = '';
    public $newTaskStartDate = '';
    public $newTaskEndDate = '';

    public function mount(): void
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadTasks();
    }

    public function loadTasks(): void
    {
        $employeeId = Auth::user()->employee?->id;
        if (!$employeeId) {
            $this->tasks = [];
            return;
        }

        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->endOfMonth();

        $this->tasks = Task::with('employees')
            ->whereHas('employees', function ($query) use ($employeeId) {
                $query->where('employees.id', $employeeId);
            })
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                        $q->where('start_date', '<=', $startOfMonth)
                          ->where('end_date', '>=', $endOfMonth);
                    });
            })
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'start' => $task->start_date->format('Y-m-d'),
                    'end' => $task->end_date->format('Y-m-d'),
                    'status' => $task->status,
                    'description' => $task->description,
                    'proof_images' => $task->proof_images ?? [],
                    'notes' => $task->notes,
                ];
            })
            ->toArray();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadTasks();
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadTasks();
    }

    public function goToToday(): void
    {
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadTasks();
    }

    public function openTask($taskId): void
    {
        $this->selectedTask = Task::with('employees')->find($taskId);
        $this->showUploadModal = true;
    }

    public function closeModal(): void
    {
        $this->showUploadModal = false;
        $this->selectedTask = null;
        $this->proofImages = [];
        $this->notes = '';
    }

    public function uploadProof(): void
    {
        if (!$this->selectedTask) {
            return;
        }

        $proofImages = $this->selectedTask->proof_images ?? [];
        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';

        // Upload new images
        foreach ($this->proofImages as $image) {
            $path = $image->store('tasks/proofs', $disk);
            $proofImages[] = $path;
        }

        // Update task
        $this->selectedTask->update([
            'proof_images' => $proofImages,
            'notes' => $this->notes,
            'status' => $this->selectedTask->status === 'pending' ? 'in_progress' : $this->selectedTask->status,
        ]);

        $this->loadTasks();
        $this->closeModal();
        
        \Filament\Notifications\Notification::make()
            ->title('Bukti pekerjaan berhasil diupload')
            ->success()
            ->send();
    }

    public function createTask(): void
    {
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskStartDate' => 'required|date',
            'newTaskEndDate' => 'required|date|after_or_equal:newTaskStartDate',
        ]);

        $employee = Auth::user()->employee;
        if (!$employee) {
            \Filament\Notifications\Notification::make()
                ->title('Error: Employee tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $task = Task::create([
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription,
            'start_date' => $this->newTaskStartDate,
            'end_date' => $this->newTaskEndDate,
            'status' => 'pending',
            'created_by' => Auth::id(),
            'is_self_assigned' => true,
        ]);

        $task->employees()->attach($employee->id);

        $this->reset(['newTaskTitle', 'newTaskDescription', 'newTaskStartDate', 'newTaskEndDate', 'showCreateModal']);
        $this->loadTasks();

        \Filament\Notifications\Notification::make()
            ->title('Pekerjaan berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['newTaskTitle', 'newTaskDescription', 'newTaskStartDate', 'newTaskEndDate']);
    }
}
