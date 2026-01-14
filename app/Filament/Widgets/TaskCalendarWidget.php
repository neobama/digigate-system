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

    public function getTaskPositions(): array
    {
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $startDate = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endDate = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);
        
        $taskPositions = [];
        
        foreach ($this->tasks as $task) {
            $taskStart = Carbon::parse($task['start']);
            $taskEnd = Carbon::parse($task['end']);
            
            // Calculate which week row this task should be in
            $weekStart = $taskStart->copy()->startOfWeek(Carbon::MONDAY);
            $weekNumber = (int) $weekStart->diffInWeeks($startDate);
            
            // Calculate column start (0-6 for Mon-Sun)
            $columnStart = $taskStart->dayOfWeek == 0 ? 6 : $taskStart->dayOfWeek - 1;
            
            // Calculate span (number of days)
            $span = $taskStart->diffInDays($taskEnd) + 1;
            
            // Clamp to visible calendar range
            if ($taskStart->lt($startDate)) {
                $columnStart = 0;
                $span = $taskEnd->diffInDays($startDate) + 1;
                if ($span > 7) $span = 7;
            }
            
            if ($taskEnd->gt($endDate)) {
                $maxSpan = 7 - $columnStart;
                $span = min($span, $maxSpan);
            }
            
            // Ensure span doesn't exceed week boundary
            $maxSpanInWeek = 7 - $columnStart;
            $span = min($span, $maxSpanInWeek);
            
            $taskPositions[] = [
                'task' => $task,
                'week' => $weekNumber,
                'column' => $columnStart,
                'span' => max(1, $span),
            ];
        }
        
        return $taskPositions;
    }
}
