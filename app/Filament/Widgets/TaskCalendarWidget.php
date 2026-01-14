<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class TaskCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.task-calendar-widget';
    protected static ?int $sort = 3;
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
                'date' => $currentDate->copy(),
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
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $startDate = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endDate = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);
        
        $days = $this->getCalendarDays();
        $taskBars = [];
        $processedTaskIds = []; // Track processed tasks to avoid duplicates
        
        foreach ($this->tasks as $task) {
            // Skip if this task ID was already processed
            if (in_array($task['id'], $processedTaskIds)) {
                continue;
            }
            
            $taskStart = Carbon::parse($task['start']);
            $taskEnd = Carbon::parse($task['end']);
            
            // Find start day index - only if task starts within visible calendar range
            $startDayIndex = null;
            foreach ($days as $idx => $day) {
                if ($day['date']->format('Y-m-d') == $taskStart->format('Y-m-d')) {
                    $startDayIndex = $idx;
                    break;
                }
            }
            
            // If task doesn't start in visible range, find first visible day
            if ($startDayIndex === null) {
                foreach ($days as $idx => $day) {
                    if ($day['date']->format('Y-m-d') >= $taskStart->format('Y-m-d') && 
                        $day['date']->format('Y-m-d') <= $taskEnd->format('Y-m-d')) {
                        $startDayIndex = $idx;
                        break;
                    }
                }
            }
            
            if ($startDayIndex !== null) {
                // Calculate span - count days from start to end (within visible range)
                $span = 1; // Start with 1 for the start day
                $currentIdx = $startDayIndex + 1; // Start checking from next day
                
                // Count how many days from start to end (within visible range)
                while ($currentIdx < count($days)) {
                    if ($days[$currentIdx]['date']->gt($taskEnd)) {
                        break;
                    }
                    $span++;
                    $currentIdx++;
                    // Limit to reasonable span
                    if ($span >= 35) break;
                }
                
                // Calculate row position - based on calendar week row, then add offset for overlaps
                $baseRow = intval($startDayIndex / 7); // Which week row (0-based) in the calendar
                
                // Check for overlaps in the same week row and find available row offset
                $rowOffset = 0;
                $placed = false;
                while (!$placed && $rowOffset < 10) {
                    $row = $baseRow + $rowOffset;
                    $canPlace = true;
                    
                    for ($i = 0; $i < $span && ($startDayIndex + $i) < count($days); $i++) {
                        $checkIdx = $startDayIndex + $i;
                        // Check if this position is already taken by another task in the same row
                        foreach ($taskBars as $existingBar) {
                            $existingBaseRow = intval($existingBar['startIndex'] / 7);
                            $existingRowOffset = $existingBar['row'] - $existingBaseRow;
                            $existingRow = $existingBaseRow + $existingRowOffset;
                            
                            if ($existingRow == $row) {
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
                        $rowOffset++;
                    }
                }
                
                $row = $baseRow + $rowOffset;
                
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
}
