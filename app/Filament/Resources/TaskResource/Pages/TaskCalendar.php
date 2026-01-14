<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;

class TaskCalendar extends Page
{
    protected static string $resource = TaskResource::class;
    
    protected static string $view = 'filament.resources.task-resource.pages.task-calendar';
    
    protected static ?string $title = 'Kalender Pekerjaan';
    
    protected static ?string $navigationLabel = 'Kalender';

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
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->endOfMonth();

        $this->tasks = Task::with('employees')
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
            $days[] = [
                'date' => $currentDate->copy()->startOfDay(),
                'day' => $currentDate->day,
                'isCurrentMonth' => $currentDate->month == $this->currentMonth,
                'isToday' => $currentDate->isToday(),
            ];
            $currentDate->addDay();
        }
        
        return $days;
    }

    public function getTaskBars(): array
    {
        $days = $this->getCalendarDays();
        $taskBars = [];
        $processedTaskIds = []; // Track processed tasks to avoid duplicates
        
        foreach ($this->tasks as $task) {
            // Skip if this task ID was already processed
            if (in_array($task['id'], $processedTaskIds)) {
                continue;
            }
            
            $taskStart = Carbon::parse($task['start'])->startOfDay();
            $taskEnd = Carbon::parse($task['end'])->startOfDay();
            
            // Find start day index - match exact date using format comparison for accuracy
            $startDayIndex = null;
            $taskStartStr = $taskStart->format('Y-m-d');
            
            foreach ($days as $idx => $day) {
                $dayDateStr = $day['date']->format('Y-m-d');
                if ($dayDateStr === $taskStartStr) {
                    $startDayIndex = $idx;
                    break;
                }
            }
            
            // If task doesn't start in visible range, find first visible day that overlaps
            if ($startDayIndex === null) {
                $taskEndStr = $taskEnd->format('Y-m-d');
                foreach ($days as $idx => $day) {
                    $dayDateStr = $day['date']->format('Y-m-d');
                    // Check if this day is within task range
                    if ($dayDateStr >= $taskStartStr && $dayDateStr <= $taskEndStr) {
                        $startDayIndex = $idx;
                        break;
                    }
                }
            }
            
            if ($startDayIndex !== null) {
                // Calculate span - count days from start to end (inclusive, within visible range)
                $span = 1; // Start with 1 for the start day
                $currentIdx = $startDayIndex;
                
                // Count how many days from start to end (inclusive)
                $taskEndStr = $taskEnd->format('Y-m-d');
                while ($currentIdx < count($days) - 1) {
                    $currentIdx++;
                    $dayDateStr = $days[$currentIdx]['date']->format('Y-m-d');
                    
                    // Check if this day is still within task range
                    if ($dayDateStr > $taskEndStr) {
                        break;
                    }
                    // If this day is within range, include it
                    if ($dayDateStr <= $taskEndStr) {
                        $span++;
                    }
                    // Limit to reasonable span
                    if ($span >= 35) break;
                }
                
                // Calculate row position (to avoid overlaps)
                $row = 0;
                $placed = false;
                while (!$placed && $row < 10) {
                    $canPlace = true;
                    for ($i = 0; $i < $span && ($startDayIndex + $i) < count($days); $i++) {
                        $checkIdx = $startDayIndex + $i;
                        // Check if this position is already taken
                        foreach ($taskBars as $existingBar) {
                            if ($existingBar['row'] == $row) {
                                $existingStart = $existingBar['startIndex'];
                                $existingSpan = $existingBar['span'];
                                $existingEnd = $existingStart + $existingSpan - 1;
                                
                                if (($checkIdx >= $existingStart && $checkIdx <= $existingEnd) ||
                                    ($startDayIndex <= $existingEnd && ($startDayIndex + $span - 1) >= $existingStart)) {
                                    $canPlace = false;
                                    break 2;
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
                
                $taskBars[] = [
                    'task' => $task,
                    'startIndex' => $startDayIndex,
                    'span' => $span,
                    'row' => $row,
                ];
                
                // Mark this task as processed
                $processedTaskIds[] = $task['id'];
            }
        }
        
        return $taskBars;
    }

    public function getMaxRows(): int
    {
        $taskBars = $this->getTaskBars();
        if (empty($taskBars)) {
            return 0;
        }
        return max(array_column($taskBars, 'row')) + 1;
    }

    public function getFullCalendarEvents(): array
    {
        return array_map(function ($task) {
            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'start' => $task['start'],
                'end' => Carbon::parse($task['end'])->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'status' => $task['status'],
                'employees' => $task['employees'],
                'editUrl' => \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]),
            ];
        }, $this->tasks);
    }
}
