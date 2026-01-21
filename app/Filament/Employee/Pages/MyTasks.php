<?php

namespace App\Filament\Employee\Pages;

use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MyTasks extends Page implements HasForms
{
    use InteractsWithForms;
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
    public $notes = '';
    public $proof_images = []; // Required for Filament FileUpload component - must match field name
    public $selectedEmployees = []; // For managing employee assignment
    public $showAddEmployeeSection = false; // Toggle for add employee section
    
    // Form untuk create task
    public $newTaskTitle = '';
    public $newTaskDescription = '';
    public $newTaskStartDate = '';
    public $newTaskEndDate = '';
    public $newTaskStartTime = '';
    public $newTaskEndTime = '';
    public $newTaskEmployees = []; // Selected employees for new task

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
                    'employees' => $task->employees->pluck('name')->toArray(),
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
        
        // Get current employee's proof and notes from pivot table
        $currentEmployee = Auth::user()->employee;
        if ($currentEmployee) {
            $pivotData = $this->selectedTask->employees()
                ->where('employees.id', $currentEmployee->id)
                ->first();
            $proofImagesRaw = $pivotData->pivot->proof_images ?? [];
            // Ensure it's an array (handle JSON string case)
            if (is_string($proofImagesRaw)) {
                $this->proof_images = json_decode($proofImagesRaw, true) ?? [];
            } elseif (is_array($proofImagesRaw)) {
                $this->proof_images = $proofImagesRaw;
            } else {
                $this->proof_images = [];
            }
            $this->notes = $pivotData->pivot->notes ?? '';
        } else {
            $this->proof_images = [];
            $this->notes = '';
        }
        
        $this->selectedEmployees = $this->selectedTask->employees->pluck('id')->toArray();
        $this->showAddEmployeeSection = false;
        
        // Fill form
        $this->form->fill([
            'proof_images' => $this->proof_images,
            'notes' => $this->notes,
        ]);
    }

    public function closeModal(): void
    {
        $this->showUploadModal = false;
        $this->selectedTask = null;
        $this->notes = '';
        $this->proof_images = [];
        $this->selectedEmployees = [];
        $this->showAddEmployeeSection = false;
        $this->form->fill();
    }
    
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('proof_images')
                ->label('Upload Foto Bukti Pekerjaan')
                ->image()
                ->directory('tasks/proofs')
                ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                ->visibility('public')
                ->imageEditor()
                ->multiple()
                ->maxFiles(10)
                ->acceptedFileTypes(['image/*'])
                ->required()
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function ($state) {
                    $this->proof_images = is_array($state) ? $state : [];
                }),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan (Opsional)')
                ->rows(3)
                ->placeholder('Tambahkan catatan tentang pekerjaan ini...')
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function ($state) {
                    $this->notes = $state ?? '';
                }),
        ];
    }
    
    public function updateStatus($status): void
    {
        if (!$this->selectedTask) {
            return;
        }
        
        $this->selectedTask->update([
            'status' => $status,
        ]);
        
        $this->loadTasks();
        
        \Filament\Notifications\Notification::make()
            ->title('Status pekerjaan berhasil diubah')
            ->success()
            ->send();
    }

    public function uploadProof(): void
    {
        if (!$this->selectedTask) {
            return;
        }

        try {
            // Validate form first and get state
            $formData = $this->form->getState();
            // Sync to properties
            $this->proof_images = $formData['proof_images'] ?? [];
            $this->notes = $formData['notes'] ?? '';
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Form validation failed, Filament will show errors automatically
            return;
        }

        // Use properties
        $proofImages = $this->proof_images;
        $notes = $this->notes;

        // Validate that proof images are required
        if (empty($proofImages)) {
            \Filament\Notifications\Notification::make()
                ->title('Foto bukti wajib diupload')
                ->warning()
                ->send();
            return;
        }

        // Get current employee
        $currentEmployee = Auth::user()->employee;
        if (!$currentEmployee) {
            \Filament\Notifications\Notification::make()
                ->title('Error: Employee tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        // Check if upload is late (after end_date)
        $today = Carbon::today();
        $taskEndDate = Carbon::parse($this->selectedTask->end_date)->startOfDay();
        $isLate = $today->greaterThan($taskEndDate);

        // Get existing proof images for this employee from pivot table
        $pivotData = $this->selectedTask->employees()
            ->where('employees.id', $currentEmployee->id)
            ->first();
        $proofImagesRaw = $pivotData && $pivotData->pivot ? ($pivotData->pivot->proof_images ?? []) : [];
        // Ensure it's an array (handle JSON string case)
        if (is_string($proofImagesRaw)) {
            $existingProofImages = json_decode($proofImagesRaw, true) ?? [];
        } elseif (is_array($proofImagesRaw)) {
            $existingProofImages = $proofImagesRaw;
        } else {
            $existingProofImages = [];
        }
        
        $isS3 = config('filesystems.default') === 's3';
        $allProofImages = [];

        // Process images - Filament FileUpload already handles upload, we just need to move to S3 if needed
        try {
            foreach ($proofImages as $proofImage) {
                $path = $proofImage;
                
                // If using S3 and file is in local storage, move to S3
                if ($isS3 && Storage::disk('public')->exists($proofImage)) {
                    $content = Storage::disk('public')->get($proofImage);
                    $s3Path = Storage::disk('s3_public')->put($proofImage, $content, 'public');
                    if ($s3Path) {
                        Storage::disk('public')->delete($proofImage);
                        $path = $s3Path;
                    }
                }
                
                $allProofImages[] = $path;
            }
            
            // Merge with existing images (keep existing ones)
            $allProofImages = array_merge($existingProofImages, $allProofImages);
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error saat upload foto: ' . $e->getMessage())
                ->danger()
                ->send();
            return;
        }

        // Update pivot table with proof images, notes, and upload timestamp for this employee
        $this->selectedTask->employees()->updateExistingPivot($currentEmployee->id, [
            'proof_images' => $allProofImages,
            'notes' => $notes,
            'proof_uploaded_at' => now(),
        ]);

        // Reload task to get updated pivot data
        $this->selectedTask->load('employees');

        // Check if all assigned employees have submitted proof
        $allEmployees = $this->selectedTask->employees;
        $employeesWithProof = $allEmployees->filter(function ($employee) {
            $proofImagesRaw = $employee->pivot->proof_images ?? [];
            // Ensure it's an array (handle JSON string case)
            if (is_string($proofImagesRaw)) {
                $pivotProof = json_decode($proofImagesRaw, true) ?? [];
            } elseif (is_array($proofImagesRaw)) {
                $pivotProof = $proofImagesRaw;
            } else {
                $pivotProof = [];
            }
            return !empty($pivotProof) && is_array($pivotProof) && count($pivotProof) > 0;
        });

        // Determine new status based on current status and proof submission
        $newStatus = $this->selectedTask->status;
        if ($this->selectedTask->status === 'failed') {
            // If failed and uploading proof, change to late (since it's after end_date)
            $newStatus = 'late';
        } elseif ($this->selectedTask->status === 'pending') {
            // If pending and uploading proof, change to in_progress (or late if after end_date)
            $newStatus = $isLate ? 'late' : 'in_progress';
        } elseif ($this->selectedTask->status === 'in_progress') {
            // Only mark as completed if ALL employees have submitted proof
            if ($employeesWithProof->count() === $allEmployees->count() && $allEmployees->count() > 0) {
                $newStatus = $isLate ? 'late' : 'completed';
            }
            // Otherwise, check if should be marked as late
            elseif ($isLate) {
                $newStatus = 'late';
            }
            // Otherwise, stay in_progress
        } elseif ($this->selectedTask->status === 'completed') {
            // If already completed but someone submits late, change to late
            if ($isLate) {
                $newStatus = 'late';
            }
            // Otherwise, stay completed
        } elseif ($this->selectedTask->status === 'late') {
            // If already late, check if should stay late or change to completed
            if ($employeesWithProof->count() === $allEmployees->count() && $allEmployees->count() > 0 && !$isLate) {
                // All submitted and not late anymore (shouldn't happen, but just in case)
                $newStatus = 'completed';
            }
            // Otherwise, stay late
        }
        // If cancelled, keep the status

        // Update task status
        $this->selectedTask->update([
            'status' => $newStatus,
        ]);

        // Create appropriate notification message BEFORE closing modal
        // (because closeModal() sets selectedTask to null)
        if ($newStatus === 'completed') {
            $message = 'Bukti pekerjaan berhasil diupload. Status berubah ke Completed (semua karyawan sudah submit).';
        } elseif ($newStatus === 'late') {
            $wasFailed = $this->selectedTask->status === 'failed';
            if ($allEmployees->count() > 1) {
                $remaining = $allEmployees->count() - $employeesWithProof->count();
                if ($remaining > 0) {
                    $message = $wasFailed 
                        ? "Bukti pekerjaan berhasil diupload. Status berubah dari Failed ke Late. Masih menunggu {$remaining} karyawan untuk submit bukti."
                        : "Bukti pekerjaan berhasil diupload (TERLAMBAT). Status: Late. Masih menunggu {$remaining} karyawan untuk submit bukti.";
                } else {
                    $message = $wasFailed
                        ? 'Bukti pekerjaan berhasil diupload. Status berubah dari Failed ke Late (semua karyawan sudah submit).'
                        : 'Bukti pekerjaan berhasil diupload (TERLAMBAT). Status: Late (semua karyawan sudah submit).';
                }
            } else {
                $message = $wasFailed
                    ? 'Bukti pekerjaan berhasil diupload. Status berubah dari Failed ke Late.'
                    : 'Bukti pekerjaan berhasil diupload (TERLAMBAT). Status berubah ke Late.';
            }
        } elseif ($newStatus === 'in_progress') {
            if ($allEmployees->count() > 1) {
                $remaining = $allEmployees->count() - $employeesWithProof->count();
                $message = "Bukti pekerjaan berhasil diupload. Status: In Progress. Masih menunggu {$remaining} karyawan untuk submit bukti.";
            } else {
                $message = 'Bukti pekerjaan berhasil diupload. Status berubah ke In Progress.';
            }
        } else {
            $message = 'Bukti pekerjaan berhasil diupload.';
        }
        
        $this->loadTasks();
        $this->closeModal();
        
        \Filament\Notifications\Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    public function createTask(): void
    {
        $rules = [
            'newTaskTitle' => 'required|string|max:255',
            'newTaskStartDate' => 'required|date',
            'newTaskEndDate' => 'required|date|after_or_equal:newTaskStartDate',
        ];

        // If same day, require time fields
        if ($this->newTaskStartDate === $this->newTaskEndDate) {
            $rules['newTaskStartTime'] = 'required|date_format:H:i';
            $rules['newTaskEndTime'] = 'required|date_format:H:i';
        }

        $this->validate($rules);

        // Custom validation: end time must be after start time for same day tasks
        if ($this->newTaskStartDate === $this->newTaskEndDate) {
            if (!empty($this->newTaskStartTime) && !empty($this->newTaskEndTime)) {
                if (strtotime($this->newTaskEndTime) <= strtotime($this->newTaskStartTime)) {
                    \Filament\Notifications\Notification::make()
                        ->title('Jam selesai harus setelah jam mulai')
                        ->danger()
                        ->send();
                    return;
                }
            }
        }

        $employee = Auth::user()->employee;
        if (!$employee) {
            \Filament\Notifications\Notification::make()
                ->title('Error: Employee tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $taskData = [
            'title' => $this->newTaskTitle,
            'description' => $this->newTaskDescription,
            'start_date' => $this->newTaskStartDate,
            'end_date' => $this->newTaskEndDate,
            'status' => 'pending',
            'created_by' => Auth::id(),
            'is_self_assigned' => true,
        ];

        // Add time if same day
        if ($this->newTaskStartDate === $this->newTaskEndDate) {
            $taskData['start_time'] = $this->newTaskStartTime;
            $taskData['end_time'] = $this->newTaskEndTime;
        }

        $task = Task::create($taskData);

        // Attach current employee (creator) and selected employees
        $employeeIds = [$employee->id];
        if (!empty($this->newTaskEmployees)) {
            // Merge with selected employees, remove duplicates
            $employeeIds = array_unique(array_merge($employeeIds, $this->newTaskEmployees));
        }
        
        $task->employees()->attach($employeeIds);
        
        // Send WhatsApp notifications to assigned employees (excluding creator)
        $whatsapp = app(\App\Services\WhatsAppService::class);
        $assignedEmployees = \App\Models\Employee::whereIn('id', $employeeIds)
            ->where('id', '!=', $employee->id) // Exclude creator
            ->get();
        
        foreach ($assignedEmployees as $assignedEmployee) {
            if (!empty($assignedEmployee->phone_number)) {
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
                
                $whatsapp->sendMessage($assignedEmployee->phone_number, $message);
            }
        }

        $this->reset(['newTaskTitle', 'newTaskDescription', 'newTaskStartDate', 'newTaskEndDate', 'newTaskStartTime', 'newTaskEndTime', 'newTaskEmployees', 'showCreateModal']);
        $this->loadTasks();

        \Filament\Notifications\Notification::make()
            ->title('Pekerjaan berhasil ditambahkan')
            ->success()
            ->send();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['newTaskTitle', 'newTaskDescription', 'newTaskStartDate', 'newTaskEndDate', 'newTaskStartTime', 'newTaskEndTime', 'newTaskEmployees']);
    }

    public function updatedNewTaskStartDate(): void
    {
        // Reset time if dates change
        if ($this->newTaskStartDate !== $this->newTaskEndDate) {
            $this->newTaskStartTime = '';
            $this->newTaskEndTime = '';
        }
    }

    public function updatedNewTaskEndDate(): void
    {
        // Reset time if dates change
        if ($this->newTaskStartDate !== $this->newTaskEndDate) {
            $this->newTaskStartTime = '';
            $this->newTaskEndTime = '';
        }
    }

    public function getAvailableEmployeesForNewTask()
    {
        $currentEmployee = Auth::user()->employee;
        if (!$currentEmployee) {
            return collect([]);
        }
        
        return \App\Models\Employee::where('is_active', true)
            ->where('id', '!=', $currentEmployee->id) // Exclude current employee (will be added automatically)
            ->orderBy('name')
            ->get();
    }

    public function canManageEmployees(): bool
    {
        if (!$this->selectedTask) {
            return false;
        }
        
        $currentEmployee = Auth::user()->employee;
        if (!$currentEmployee) {
            return false;
        }
        
        // Check if task is self assigned and created by current user
        return $this->selectedTask->is_self_assigned 
            && $this->selectedTask->created_by === Auth::id()
            && $this->selectedTask->employees->contains($currentEmployee->id);
    }

    public function addEmployees(): void
    {
        if (!$this->selectedTask || !$this->canManageEmployees()) {
            \Filament\Notifications\Notification::make()
                ->title('Tidak memiliki izin untuk menambahkan karyawan')
                ->danger()
                ->send();
            return;
        }

        if (empty($this->selectedEmployees)) {
            \Filament\Notifications\Notification::make()
                ->title('Pilih setidaknya satu karyawan')
                ->warning()
                ->send();
            return;
        }

        $currentEmployeeIds = $this->selectedTask->employees->pluck('id')->toArray();
        $newEmployeeIds = array_diff($this->selectedEmployees, $currentEmployeeIds);

        if (empty($newEmployeeIds)) {
            \Filament\Notifications\Notification::make()
                ->title('Karyawan yang dipilih sudah ditambahkan sebelumnya')
                ->warning()
                ->send();
            return;
        }

        // Add new employees to task
        $this->selectedTask->employees()->syncWithoutDetaching($newEmployeeIds);
        
        // Reload task with employees
        $this->selectedTask->load('employees');
        
        // Send WhatsApp notifications to newly added employees
        $whatsapp = app(\App\Services\WhatsAppService::class);
        $newEmployees = \App\Models\Employee::whereIn('id', $newEmployeeIds)->get();
        
        foreach ($newEmployees as $employee) {
            if (!empty($employee->phone_number)) {
                $message = "📋 *Task Baru Ditetapkan*\n\n";
                $message .= "Judul: {$this->selectedTask->title}\n";
                
                if ($this->selectedTask->description) {
                    $message .= "Deskripsi: {$this->selectedTask->description}\n";
                }
                
                $message .= "Tanggal: " . $this->selectedTask->start_date->format('d/m/Y');
                
                if ($this->selectedTask->start_date->format('Y-m-d') !== $this->selectedTask->end_date->format('Y-m-d')) {
                    $message .= " - " . $this->selectedTask->end_date->format('d/m/Y');
                }
                
                $message .= "\nStatus: " . ucfirst($this->selectedTask->status);
                
                $whatsapp->sendMessage($employee->phone_number, $message);
            }
        }

        $this->loadTasks();
        $this->selectedEmployees = [];
        $this->showAddEmployeeSection = false;
        
        \Filament\Notifications\Notification::make()
            ->title('Karyawan berhasil ditambahkan ke task')
            ->success()
            ->send();
    }

    public function getAvailableEmployees()
    {
        if (!$this->selectedTask) {
            return collect([]);
        }

        $currentEmployeeIds = $this->selectedTask->employees->pluck('id')->toArray();
        
        return \App\Models\Employee::where('is_active', true)
            ->whereNotIn('id', $currentEmployeeIds)
            ->orderBy('name')
            ->get();
    }

    public function getCalendarDays(): array
    {
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $startDate = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endDate = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);
        
        $days = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateCopy = $currentDate->copy()->startOfDay();
            $days[] = [
                'date' => $dateCopy,
                'day' => $currentDate->day,
                'isCurrentMonth' => $currentDate->month == $this->currentMonth,
                'isToday' => $currentDate->isToday(),
            ];
            $currentDate->addDay();
        }
        
        return $days;
    }

    public function getTasksByDay(): array
    {
        $days = $this->getCalendarDays();
        $tasksByDay = [];
        
        // Initialize empty arrays for each day
        foreach ($days as $idx => $day) {
            $tasksByDay[$idx] = [];
        }
        
        // First pass: collect all tasks with their positions
        $taskBars = [];
        foreach ($this->tasks as $task) {
            $taskStartStr = $task['start'];
            $taskEndStr = $task['end'];
            
            // Find the start day index
            $startDayIndex = null;
            foreach ($days as $idx => $day) {
                $dayDateStr = $day['date']->format('Y-m-d');
                if ($dayDateStr === $taskStartStr) {
                    $startDayIndex = $idx;
                    break;
                }
            }
            
            // If task doesn't start in visible range, find first visible day that overlaps
            if ($startDayIndex === null) {
                foreach ($days as $idx => $day) {
                    $dayDateStr = $day['date']->format('Y-m-d');
                    if ($dayDateStr >= $taskStartStr && $dayDateStr <= $taskEndStr) {
                        $startDayIndex = $idx;
                        break;
                    }
                }
            }
            
            // Only add task if it's in visible range
            if ($startDayIndex !== null) {
                // Calculate span from start to end (inclusive)
                $span = 1; // Start with the start day itself
                $currentIdx = $startDayIndex;
                
                // Count days until we reach or pass the end date
                while ($currentIdx < count($days) - 1) {
                    $currentIdx++;
                    $nextDayStr = $days[$currentIdx]['date']->format('Y-m-d');
                    
                    // Stop if we've passed the end date
                    if ($nextDayStr > $taskEndStr) {
                        break;
                    }
                    
                    // Include this day if it's within the task range
                    if ($nextDayStr <= $taskEndStr) {
                        $span++;
                    }
                    
                    // Safety limit
                    if ($span >= 42) break; // Max 6 weeks
                }
                
                $taskBars[] = [
                    'task' => $task,
                    'startIndex' => $startDayIndex,
                    'span' => $span,
                ];
            }
        }
        
        // Second pass: calculate row positions to avoid overlaps
        // Sort tasks by start index, then by end index (shorter tasks first when same start)
        usort($taskBars, function($a, $b) {
            $startCompare = $a['startIndex'] <=> $b['startIndex'];
            if ($startCompare !== 0) {
                return $startCompare;
            }
            // If same start, shorter tasks first
            return ($a['startIndex'] + $a['span']) <=> ($b['startIndex'] + $b['span']);
        });
        
        // Track all placed tasks with their ranges for overlap detection
        // Group by row for faster lookup
        $placedTasksByRow = [];
        
        foreach ($taskBars as $taskBar) {
            $startIndex = $taskBar['startIndex'];
            $span = $taskBar['span'];
            $task = $taskBar['task'];
            $endIndex = min($startIndex + $span - 1, count($days) - 1);
            
            // Calculate which week rows this task spans
            $startWeekRow = intval($startIndex / 7);
            $endWeekRow = intval($endIndex / 7);
            
            // Find available row for this task (check all week rows it spans)
            // We need to find a row that's available across all week rows
            $row = 0;
            $placed = false;
            
            while (!$placed && $row < 20) {
                $canPlace = true;
                
                // Check overlap in each week row this task spans
                for ($weekRow = $startWeekRow; $weekRow <= $endWeekRow; $weekRow++) {
                    $weekRowKey = $weekRow . '_' . $row;
                    
                    if (isset($placedTasksByRow[$weekRowKey])) {
                        foreach ($placedTasksByRow[$weekRowKey] as $placedTask) {
                            $existingStart = $placedTask['startIndex'];
                            $existingEnd = $placedTask['endIndex'];
                            
                            // Calculate the segment range for this week row
                            $weekStartIndex = $weekRow * 7;
                            $weekEndIndex = min(($weekRow + 1) * 7 - 1, count($days) - 1);
                            $segmentStart = max($startIndex, $weekStartIndex);
                            $segmentEnd = min($endIndex, $weekEndIndex);
                            
                            // Check if date ranges overlap in this week row
                            if ($segmentStart <= $existingEnd && $existingStart <= $segmentEnd) {
                                $canPlace = false;
                                break 2; // Break both loops
                            }
                        }
                    }
                }
                
                if ($canPlace) {
                    $placed = true;
                } else {
                    $row++;
                }
            }
            
            // Split task into segments for each week row it spans
            $currentStartIndex = $startIndex;
            $remainingSpan = $span;
            
            while ($remainingSpan > 0 && $currentStartIndex < count($days)) {
                $currentWeekRow = intval($currentStartIndex / 7);
                $currentCol = $currentStartIndex % 7;
                
                // Calculate how many days are left in this week row
                $daysLeftInWeek = 7 - $currentCol;
                $segmentSpan = min($remainingSpan, $daysLeftInWeek);
                $segmentEndIndex = $currentStartIndex + $segmentSpan - 1;
                
                // Add segment to the appropriate day (using the same row number for all segments)
                $tasksByDay[$currentStartIndex][] = [
                    'task' => $task,
                    'isStartDay' => ($currentStartIndex === $startIndex),
                    'span' => $segmentSpan,
                    'row' => $row, // Same row number for all segments
                    'startIndex' => $currentStartIndex,
                    'endIndex' => $segmentEndIndex,
                    'weekRow' => $currentWeekRow,
                ];
                
                // Track this segment for future overlap checks
                $weekRowKey = $currentWeekRow . '_' . $row;
                if (!isset($placedTasksByRow[$weekRowKey])) {
                    $placedTasksByRow[$weekRowKey] = [];
                }
                $placedTasksByRow[$weekRowKey][] = [
                    'startIndex' => $currentStartIndex,
                    'endIndex' => $segmentEndIndex,
                    'span' => $segmentSpan,
                ];
                
                // Move to next week row
                $currentStartIndex += $segmentSpan;
                $remainingSpan -= $segmentSpan;
            }
        }
        
        return $tasksByDay;
    }

    public function getMaxTasksPerDay(): int
    {
        $tasksByDay = $this->getTasksByDay();
        $maxRow = 0;
        
        foreach ($tasksByDay as $dayTasks) {
            foreach ($dayTasks as $taskInfo) {
                if (isset($taskInfo['row']) && $taskInfo['row'] > $maxRow) {
                    $maxRow = $taskInfo['row'];
                }
            }
        }
        
        return $maxRow + 1; // +1 because row is 0-based
    }
}
