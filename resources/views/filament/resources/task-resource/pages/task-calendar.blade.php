<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button 
                    type="button"
                    wire:click="previousMonth"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                >
                    ← Sebelumnya
                </button>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                </h2>
                <button 
                    type="button"
                    wire:click="nextMonth"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                >
                    Selanjutnya →
                </button>
                <button 
                    type="button"
                    wire:click="goToToday"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 transition-colors"
                >
                    Hari Ini
                </button>
            </div>
            <a 
                href="{{ \App\Filament\Resources\TaskResource::getUrl('create') }}"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 transition-colors"
            >
                + Tambah Pekerjaan
            </a>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
            <div class="grid grid-cols-7">
                <!-- Day Headers -->
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                    <div class="bg-gray-50 p-3 text-center text-sm font-semibold text-gray-700 border-b border-gray-200">
                        {{ $day }}
                    </div>
                @endforeach

                <!-- Calendar Days -->
                @php
                    $days = $this->getCalendarDays();
                @endphp

                @foreach($days as $index => $day)
                    <div class="min-h-[150px] bg-white p-2 border-r border-b border-gray-100 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50' : '' }}">
                        <div class="text-sm font-medium mb-2 {{ !$day['isCurrentMonth'] ? 'text-gray-400' : ($day['isToday'] ? 'text-primary-600 font-bold' : 'text-gray-700') }}">
                            {{ $day['day'] }}
                        </div>
                        <div class="space-y-1.5 mt-2">
                            @php
                                $dayTasks = [];
                                foreach ($this->tasks as $task) {
                                    $taskStart = \Carbon\Carbon::parse($task['start']);
                                    $taskEnd = \Carbon\Carbon::parse($task['end']);
                                    
                                    // Only show task on its start day
                                    if ($day['date']->format('Y-m-d') == $taskStart->format('Y-m-d')) {
                                        // Calculate span within the week
                                        $weekStart = $day['date']->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                                        $weekEnd = $day['date']->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                                        $actualEnd = $taskEnd->lt($weekEnd) ? $taskEnd : $weekEnd;
                                        $span = $day['date']->diffInDays($actualEnd) + 1;
                                        $colInWeek = $index % 7;
                                        $span = min($span, 7 - $colInWeek);
                                        
                                        $dayTasks[] = [
                                            'task' => $task,
                                            'span' => $span,
                                        ];
                                    }
                                }
                            @endphp
                            
                            @foreach($dayTasks as $taskData)
                                @php
                                    $task = $taskData['task'];
                                    $span = $taskData['span'];
                                    $statusColors = [
                                        'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'in_progress' => 'bg-primary-100 text-primary-800 border-primary-200',
                                        'completed' => 'bg-green-100 text-green-800 border-green-200',
                                        'cancelled' => 'bg-red-100 text-red-800 border-red-200',
                                    ];
                                    $color = $statusColors[$task['status']] ?? $statusColors['pending'];
                                @endphp
                                
                                <div 
                                    class="text-xs p-2 rounded-md cursor-pointer hover:opacity-90 transition-opacity border {{ $color }}"
                                    style="position: absolute; left: 0.5rem; width: calc({{ $span }} * (100% / 7) + {{ ($span - 1) * 1 }}rem - 1rem); z-index: 10;"
                                    title="{{ $task['title'] }} - {{ implode(', ', $task['employees']) }}"
                                    onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                >
                                    <div class="font-medium truncate">{{ $task['title'] }}</div>
                                    @if(!empty($task['employees']))
                                        <div class="text-[11px] opacity-75 mt-1 truncate">
                                            {{ implode(', ', array_slice($task['employees'], 0, 2)) }}{{ count($task['employees']) > 2 ? '...' : '' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-6 text-sm pt-3 border-t border-gray-200">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-amber-100 border border-amber-200 rounded"></div>
                <span class="text-gray-600">Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-primary-100 border border-primary-200 rounded"></div>
                <span class="text-gray-600">In Progress</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-100 border border-green-200 rounded"></div>
                <span class="text-gray-600">Completed</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-100 border border-red-200 rounded"></div>
                <span class="text-gray-600">Cancelled</span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
