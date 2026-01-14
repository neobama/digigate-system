<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class TaskCalendar extends Page
{
    protected static string $view = 'filament.resources.task-resource.pages.task-calendar';
    protected static ?string $title = 'Kalender Pekerjaan';
    protected static ?string $navigationLabel = 'Kalender';
    
    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return static::getResource()::getUrl('calendar', $parameters, $isAbsolute, $panel, $tenant);
    }
    
    public static function getResource(): string
    {
        return TaskResource::class;
    }

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

    public function getTasksForDay($date): array
    {
        $dateStr = $date->format('Y-m-d');
        $tasksForDay = [];
        
        foreach ($this->tasks as $task) {
            $taskStart = Carbon::parse($task['start']);
            $taskEnd = Carbon::parse($task['end']);
            
            if ($date->between($taskStart, $taskEnd)) {
                // Calculate if this is the start day
                $isStart = $date->format('Y-m-d') == $task['start'];
                
                // Calculate span
                $startDate = Carbon::parse($task['start']);
                $endDate = Carbon::parse($task['end']);
                
                // Calculate how many days from start to end of week
                $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);
                $maxSpan = $date->diffInDays(min($endDate, $weekEnd)) + 1;
                
                $tasksForDay[] = [
                    'task' => $task,
                    'isStart' => $isStart,
                    'span' => $maxSpan,
                ];
            }
        }
        
        return $tasksForDay;
    }
}
