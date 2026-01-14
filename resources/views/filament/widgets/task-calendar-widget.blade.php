<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Kalender Pekerjaan</span>
                <a 
                    href="{{ \App\Filament\Resources\TaskResource::getUrl('calendar') }}"
                    class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                >
                    Lihat Kalender Lengkap →
                </a>
            </div>
        </x-slot>

        <div class="space-y-4">
            <!-- Calendar Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button 
                        type="button"
                        wire:click="previousMonth"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                    >
                        ←
                    </button>
                    <h3 class="text-base font-semibold text-gray-900 px-3">
                        {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                    </h3>
                    <button 
                        type="button"
                        wire:click="nextMonth"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                    >
                        →
                    </button>
                    <button 
                        type="button"
                        wire:click="goToToday"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 transition-colors ml-2"
                    >
                        Hari Ini
                    </button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                <div class="grid grid-cols-7">
                    <!-- Day Headers -->
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                        <div class="bg-gray-50 p-2 text-center text-xs font-semibold text-gray-700 border-b border-gray-200">
                            {{ $day }}
                        </div>
                    @endforeach

                    <!-- Calendar Days -->
                    @php
                        $days = $this->getCalendarDays();
                    @endphp

                    @foreach($days as $index => $day)
                        <div class="min-h-[120px] bg-white p-1.5 border-r border-b border-gray-100 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50' : '' }}">
                            <div class="text-xs font-medium mb-1 {{ !$day['isCurrentMonth'] ? 'text-gray-400' : ($day['isToday'] ? 'text-primary-600 font-bold' : 'text-gray-700') }}">
                                {{ $day['day'] }}
                            </div>
                            <div class="space-y-1 mt-1">
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
                                        class="text-xs p-1.5 rounded-md cursor-pointer hover:opacity-90 transition-opacity border {{ $color }}"
                                        style="position: absolute; left: 0.375rem; width: calc({{ $span }} * (100% / 7) + {{ ($span - 1) * 0.75 }}rem - 0.75rem); z-index: 10;"
                                        title="{{ $task['title'] }} - {{ implode(', ', $task['employees']) }}"
                                        onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                    >
                                        <div class="font-medium truncate">{{ $task['title'] }}</div>
                                        @if(!empty($task['employees']))
                                            <div class="text-[10px] opacity-75 mt-0.5 truncate">
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
            <div class="flex items-center gap-4 text-xs pt-2 border-t border-gray-200">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-amber-100 border border-amber-200 rounded"></div>
                    <span class="text-gray-600">Pending</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-primary-100 border border-primary-200 rounded"></div>
                    <span class="text-gray-600">In Progress</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-green-100 border border-green-200 rounded"></div>
                    <span class="text-gray-600">Completed</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-red-100 border border-red-200 rounded"></div>
                    <span class="text-gray-600">Cancelled</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
