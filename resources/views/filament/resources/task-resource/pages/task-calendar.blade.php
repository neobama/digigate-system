<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button 
                    type="button"
                    wire:click="previousMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    ← Sebelumnya
                </button>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                </h2>
                <button 
                    type="button"
                    wire:click="nextMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    Selanjutnya →
                </button>
                <button 
                    type="button"
                    wire:click="goToToday"
                    class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm"
                >
                    Hari Ini
                </button>
            </div>
            <a 
                href="{{ \App\Filament\Resources\TaskResource::getUrl('create') }}"
                class="px-5 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm"
            >
                + Tambah Pekerjaan
            </a>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-lg">
            <div class="grid grid-cols-7 relative">
                <!-- Day Headers -->
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 text-center text-sm font-bold text-gray-700 dark:text-gray-300 border-b-2 border-gray-200 dark:border-gray-700">
                        {{ $day }}
                    </div>
                @endforeach

                <!-- Calendar Days Container -->
                @php
                    $days = $this->getCalendarDays();
                    $tasksByDay = $this->getTasksByDay();
                    $maxTasksPerDay = $this->getMaxTasksPerDay();
                    // Calculate min height based on max tasks per day
                    // Each task bar is approximately 3rem (48px) tall
                    $minHeight = max(200, 80 + ($maxTasksPerDay * 48)); // Base height + task height in px
                @endphp

                @foreach($days as $index => $day)
                    <div 
                        class="bg-white dark:bg-gray-800 p-3 border-r border-b border-gray-200 dark:border-gray-700 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-900' : '' }}" 
                        style="min-height: {{ $minHeight }}px;"
                        data-day-index="{{ $index }}"
                        data-day-date="{{ $day['date']->format('Y-m-d') }}"
                    >
                        <div class="text-base font-semibold mb-2 {{ !$day['isCurrentMonth'] ? 'text-gray-400 dark:text-gray-600' : ($day['isToday'] ? 'text-white bg-primary-600 dark:bg-primary-500 rounded-full w-8 h-8 flex items-center justify-center' : 'text-gray-900 dark:text-gray-100') }}">
                            {{ $day['day'] }}
                        </div>
                    </div>
                @endforeach
                
                <!-- Task Bars Overlay - Absolute positioned at grid level -->
                <div class="absolute inset-0 pointer-events-none" style="margin-top: 3.5rem;">
                    @foreach($days as $index => $day)
                        @php
                            $weekRow = intval($index / 7); // Week row (0-based)
                            $tasksStartingToday = array_filter($tasksByDay[$index] ?? [], function($t) {
                                return $t['isStartDay'];
                            });
                            $taskRowInDay = 0;
                        @endphp
                        @foreach($tasksStartingToday as $taskInfo)
                            @php
                                $task = $taskInfo['task'];
                                $span = $taskInfo['span'];
                                
                                // Color definitions
                                $statusStyles = [
                                    'pending' => 'background-color: #fde68a; color: #78350f; border-color: #fbbf24;',
                                    'in_progress' => 'background-color: #fbbf24; color: #78350f; border-color: #f59e0b;',
                                    'completed' => 'background-color: #86efac; color: #14532d; border-color: #4ade80;',
                                    'cancelled' => 'background-color: #fca5a5; color: #7f1d1d; border-color: #f87171;',
                                ];
                                $darkStatusStyles = [
                                    'pending' => 'background-color: #92400e; color: #fef3c7; border-color: #d97706;',
                                    'in_progress' => 'background-color: #92400e; color: #fef3c7; border-color: #d97706;',
                                    'completed' => 'background-color: #166534; color: #dcfce7; border-color: #16a34a;',
                                    'cancelled' => 'background-color: #991b1b; color: #fee2e2; border-color: #dc2626;',
                                ];
                                $style = $statusStyles[$task['status']] ?? $statusStyles['pending'];
                                $darkStyle = $darkStatusStyles[$task['status']] ?? $darkStatusStyles['pending'];
                                
                                // Calculate position based on grid - simple and direct
                                $col = $index % 7; // Column (0-6)
                                $cellWidthPercent = 100 / 7; // 14.2857% per cell
                                $leftPercent = $col * $cellWidthPercent;
                                $widthPercent = $span * $cellWidthPercent;
                                
                                // Calculate top offset: week row * cell height + task row in day * task height
                                // Each cell has minHeight in px, convert to rem (16px = 1rem)
                                // Overlay starts at margin-top: 3.5rem (after header)
                                $cellHeightRem = $minHeight / 16; // Convert px to rem
                                $topOffset = ($weekRow * $cellHeightRem) + ($taskRowInDay * 3); // Week row + task row in day
                                $taskRowInDay++; // Increment for next task in same day
                            @endphp
                            
                            <div 
                                class="absolute text-xs p-2 rounded-lg cursor-pointer hover:opacity-90 transition-all border-2 font-medium shadow-sm task-bar-item pointer-events-auto"
                                style="
                                    left: calc({{ $leftPercent }}% + 0.75rem); 
                                    width: calc({{ $widthPercent }}% - 0.75rem - ({{ $span - 1 }} * 1px)); 
                                    top: {{ $topOffset }}rem; 
                                    z-index: {{ 10 + $span }};
                                    box-sizing: border-box;
                                    {{ $style }}
                                "
                                data-dark-style="{{ $darkStyle }}"
                                data-task-start="{{ $task['start'] }}"
                                data-task-end="{{ $task['end'] }}"
                                data-day-index="{{ $index }}"
                                title="{{ $task['title'] }} | Start: {{ $task['start'] }}, End: {{ $task['end'] }} | DayIndex: {{ $index }}, WeekRow: {{ $weekRow }}, TaskRow: {{ $taskRowInDay - 1 }}, Top: {{ $topOffset }}rem | {{ implode(', ', $task['employees']) }}"
                                onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                            >
                                <div class="font-semibold truncate mb-0.5">{{ $task['title'] }}</div>
                                @if(!empty($task['employees']))
                                    <div class="text-[10px] opacity-90 mt-0.5 truncate">
                                        {{ implode(', ', array_slice($task['employees'], 0, 2)) }}{{ count($task['employees']) > 2 ? '...' : '' }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-6 text-sm pt-4 border-t-2 border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fde68a; border-color: #fbbf24;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Pending</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fbbf24; border-color: #f59e0b;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">In Progress</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #86efac; border-color: #4ade80;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Completed</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fca5a5; border-color: #f87171;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Cancelled</span>
            </div>
        </div>
    </div>

    <script>
        // Update task bar colors based on dark mode
        function updateTaskBarColors() {
            const isDark = document.documentElement.classList.contains('dark');
            const taskBars = document.querySelectorAll('.task-bar-item');
            
            taskBars.forEach(bar => {
                if (isDark && bar.dataset.darkStyle) {
                    // Apply dark style
                    const currentStyle = bar.getAttribute('style');
                    const baseStyle = currentStyle.split(';').filter(s => !s.includes('background-color') && !s.includes('color') && !s.includes('border-color')).join(';');
                    bar.setAttribute('style', baseStyle + '; ' + bar.dataset.darkStyle);
                }
            });
        }
        
        // Initial update
        document.addEventListener('DOMContentLoaded', function() {
            updateTaskBarColors();
            
            // Watch for dark mode changes
            const observer = new MutationObserver(updateTaskBarColors);
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });
        });
    </script>
</x-filament-panels::page>
