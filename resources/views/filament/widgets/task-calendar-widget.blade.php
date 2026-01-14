<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Kalender Pekerjaan</span>
                <a 
                    href="{{ \App\Filament\Resources\TaskResource::getUrl('calendar') }}"
                    class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium"
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
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        ←
                    </button>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 px-3">
                        {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                    </h3>
                    <button 
                        type="button"
                        wire:click="nextMonth"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        →
                    </button>
                    <button 
                        type="button"
                        wire:click="goToToday"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-md hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors ml-2"
                    >
                        Hari Ini
                    </button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm relative">
                <div class="grid grid-cols-7">
                    <!-- Day Headers -->
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                        <div class="bg-gray-50 dark:bg-gray-900 p-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                            {{ $day }}
                        </div>
                    @endforeach

                    <!-- Calendar Days Container -->
                    @php
                        $days = $this->getCalendarDays();
                        $taskBars = $this->getTaskBars();
                        $maxRows = $this->getMaxRows();
                        $minHeight = max(140, 50 + ($maxRows * 36)); // Base height + row height
                    @endphp

                    @foreach($days as $index => $day)
                        <div class="bg-white dark:bg-gray-800 p-2 border-r border-b border-gray-100 dark:border-gray-700 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-900' : '' }}" style="min-height: {{ $minHeight }}px;">
                            <div class="text-xs font-medium mb-1 {{ !$day['isCurrentMonth'] ? 'text-gray-400 dark:text-gray-600' : ($day['isToday'] ? 'text-white bg-primary-600 dark:bg-primary-500 rounded-full w-6 h-6 flex items-center justify-center text-xs' : 'text-gray-700 dark:text-gray-300') }}">
                                {{ $day['day'] }}
                            </div>
                        </div>
                    @endforeach
                    
                    <!-- Task Bars Overlay -->
                    <div class="absolute inset-0 pointer-events-none" style="margin-top: 2.5rem; padding: 0.5rem;">
                        <div class="grid grid-cols-7 h-full relative">
                            @foreach($taskBars as $taskBar)
                                @php
                                    $task = $taskBar['task'];
                                    $startIndex = $taskBar['startIndex'];
                                    $span = $taskBar['span'];
                                    $row = $taskBar['row'];
                                    
                                    // Use more visible colors with better contrast
                                    $statusColors = [
                                        'pending' => 'bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 border-amber-400 dark:border-amber-600',
                                        'in_progress' => 'bg-primary-200 dark:bg-primary-800 text-primary-900 dark:text-primary-100 border-primary-400 dark:border-primary-600',
                                        'completed' => 'bg-green-200 dark:bg-green-800 text-green-900 dark:text-green-100 border-green-400 dark:border-green-600',
                                        'cancelled' => 'bg-red-200 dark:bg-red-800 text-red-900 dark:text-red-100 border-red-400 dark:border-red-600',
                                    ];
                                    $color = $statusColors[$task['status']] ?? $statusColors['pending'];
                                    
                                    // Calculate position more accurately
                                    $col = $startIndex % 7;
                                    $cellWidth = 100 / 7; // Percentage per cell
                                    $leftPercent = $col * $cellWidth;
                                    $widthPercent = $span * $cellWidth;
                                    $topOffset = $row * 2.25; // Row spacing in rem
                                @endphp
                                
                                <div 
                                    class="absolute text-xs p-1.5 rounded-md cursor-pointer hover:opacity-90 transition-all border-2 {{ $color }} pointer-events-auto shadow-sm font-medium"
                                    style="left: calc({{ $leftPercent }}% + 0.5rem); width: calc({{ $widthPercent }}% - 1rem); top: {{ $topOffset }}rem; z-index: {{ 10 + $row }};"
                                    title="{{ $task['title'] }} - {{ implode(', ', $task['employees']) }}"
                                    onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                >
                                    <div class="font-semibold truncate mb-0.5">{{ $task['title'] }}</div>
                                    @if(!empty($task['employees']))
                                        <div class="text-[10px] opacity-90 dark:opacity-80 mt-0.5 truncate">
                                            {{ implode(', ', array_slice($task['employees'], 0, 2)) }}{{ count($task['employees']) > 2 ? '...' : '' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-4 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-amber-200 dark:bg-amber-800 border border-amber-400 dark:border-amber-600 rounded"></div>
                    <span class="text-gray-600 dark:text-gray-400">Pending</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-primary-200 dark:bg-primary-800 border border-primary-400 dark:border-primary-600 rounded"></div>
                    <span class="text-gray-600 dark:text-gray-400">In Progress</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-green-200 dark:bg-green-800 border border-green-400 dark:border-green-600 rounded"></div>
                    <span class="text-gray-600 dark:text-gray-400">Completed</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 bg-red-200 dark:bg-red-800 border border-red-400 dark:border-red-600 rounded"></div>
                    <span class="text-gray-600 dark:text-gray-400">Cancelled</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
