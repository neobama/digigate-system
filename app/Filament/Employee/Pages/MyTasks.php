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
        $this->notes = $this->selectedTask->notes ?? '';
        $this->proof_images = [];
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

        $existingProofImages = $this->selectedTask->proof_images ?? [];
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

        // Determine new status based on current status
        $newStatus = $this->selectedTask->status;
        if ($this->selectedTask->status === 'pending') {
            // If pending and uploading proof, change to in_progress
            $newStatus = 'in_progress';
        } elseif ($this->selectedTask->status === 'in_progress') {
            // If already in_progress and uploading proof, change to completed
            $newStatus = 'completed';
        }
        // If already completed or cancelled, keep the status

        // Update task
        $this->selectedTask->update([
            'proof_images' => $allProofImages,
            'notes' => $notes,
            'status' => $newStatus,
        ]);

        $this->loadTasks();
        $this->closeModal();
        
        $statusMessage = $newStatus === 'completed' ? 'Status berubah ke Completed' : 'Status berubah ke In Progress';
        \Filament\Notifications\Notification::make()
            ->title('Bukti pekerjaan berhasil diupload. ' . $statusMessage)
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
