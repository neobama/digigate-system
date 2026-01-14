<?php

namespace App\Filament\Employee\Widgets;

use App\Models\Task;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EmployeeTaskCalendarWidget extends Widget
{
    protected static string $view = 'filament.employee.widgets.employee-task-calendar-widget';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public $currentMonth;
    public $currentYear;
    public $tasks = [];

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
                // Calculate span from start to end
                $span = 1;
                $currentIdx = $startDayIndex;
                while ($currentIdx < count($days) - 1) {
                    $currentIdx++;
                    $nextDayStr = $days[$currentIdx]['date']->format('Y-m-d');
                    if ($nextDayStr > $taskEndStr) {
                        break;
                    }
                    if ($nextDayStr <= $taskEndStr) {
                        $span++;
                    }
                    if ($span >= 35) break;
                }
                
                $taskBars[] = [
                    'task' => $task,
                    'startIndex' => $startDayIndex,
                    'span' => $span,
                ];
            }
        }
        
        // Second pass: calculate row positions to avoid overlaps
        // Sort tasks by start index to process them in order
        usort($taskBars, function($a, $b) {
            return $a['startIndex'] <=> $b['startIndex'];
        });
        
        // Track all placed tasks with their ranges for overlap detection
        $placedTasks = [];
        
        foreach ($taskBars as $taskBar) {
            $startIndex = $taskBar['startIndex'];
            $span = $taskBar['span'];
            $task = $taskBar['task'];
            $endIndex = min($startIndex + $span - 1, count($days) - 1);
            
            // Find available row for this task
            $row = 0;
            $placed = false;
            while (!$placed && $row < 20) {
                $canPlace = true;
                
                // Check if this row position conflicts with any existing task in the same row
                // We need to check if the date ranges overlap
                foreach ($placedTasks as $placedTask) {
                    if ($placedTask['row'] == $row) {
                        $existingStart = $placedTask['startIndex'];
                        $existingEnd = $placedTask['endIndex'];
                        
                        // Check if date ranges overlap
                        // Two ranges overlap if: !(end1 < start2 || start1 > end2)
                        if (!($endIndex < $existingStart || $startIndex > $existingEnd)) {
                            $canPlace = false;
                            break;
                        }
                    }
                }
                
                if ($canPlace) {
                    $placed = true;
                } else {
                    $row++;
                }
            }
            
            // Add task to start day with row information
            $tasksByDay[$startIndex][] = [
                'task' => $task,
                'isStartDay' => true,
                'span' => $span,
                'row' => $row,
                'startIndex' => $startIndex,
                'endIndex' => $endIndex,
            ];
            
            // Track this placed task for future overlap checks
            $placedTasks[] = [
                'row' => $row,
                'startIndex' => $startIndex,
                'endIndex' => $endIndex,
                'span' => $span,
            ];
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
