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
