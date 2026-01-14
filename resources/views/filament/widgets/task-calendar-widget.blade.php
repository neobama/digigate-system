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

        <div class="space-y-3">
            <!-- Calendar Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button 
                        type="button"
                        wire:click="previousMonth"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                    >
                        ←
                    </button>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 px-3">
                        {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                    </h3>
                    <button 
                        type="button"
                        wire:click="nextMonth"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                    >
                        →
                    </button>
                    <button 
                        type="button"
                        wire:click="goToToday"
                        class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm ml-2"
                    >
                        Hari Ini
                    </button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-lg relative">
                <div class="grid grid-cols-7" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                    <!-- Day Headers -->
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                        <div class="bg-gray-50 dark:bg-gray-900 p-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 border-b-2 border-gray-200 dark:border-gray-700">
                            {{ $day }}
                        </div>
                    @endforeach

                    <!-- Calendar Days Container -->
                    @php
                        $days = $this->getCalendarDays();
                        $tasksByDay = $this->getTasksByDay();
                        $maxTasksPerDay = $this->getMaxTasksPerDay();
                        // Calculate min height based on max tasks per day - smaller for widget
                        $minHeight = max(120, 50 + ($maxTasksPerDay * 36)); // Base height + task height in px
                    @endphp

                    @foreach($days as $index => $day)
                        <div 
                            class="bg-white dark:bg-gray-800 p-2 border-r border-b border-gray-200 dark:border-gray-700 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-900' : '' }}" 
                            style="min-height: {{ $minHeight }}px;"
                        >
                            <div class="text-xs font-semibold mb-1 {{ !$day['isCurrentMonth'] ? 'text-gray-400 dark:text-gray-600' : ($day['isToday'] ? 'text-white bg-primary-600 dark:bg-primary-500 rounded-full w-6 h-6 flex items-center justify-center' : 'text-gray-900 dark:text-gray-100') }}">
                                {{ $day['day'] }}
                            </div>
                        </div>
                    @endforeach
                    
                    <!-- Task Bars Overlay - Absolute positioned at grid level -->
                    <div class="absolute inset-0 pointer-events-none" style="margin-top: 2.5rem;">
                        @foreach($days as $index => $day)
                            @php
                                $weekRow = intval($index / 7); // Week row (0-based)
                                $tasksStartingToday = array_filter($tasksByDay[$index] ?? [], function($t) {
                                    return $t['isStartDay'];
                                });
                            @endphp
                            @foreach($tasksStartingToday as $taskInfo)
                                @php
                                    $task = $taskInfo['task'];
                                    $span = $taskInfo['span'];
                                    $row = $taskInfo['row'] ?? 0;
                                    
                                    // Color definitions based on status
                                    $statusColors = [
                                        'pending' => [
                                            'bg' => '#fde68a',
                                            'text' => '#78350f',
                                            'border' => '#fbbf24',
                                            'dark_bg' => '#92400e',
                                            'dark_text' => '#fef3c7',
                                            'dark_border' => '#d97706',
                                        ],
                                        'in_progress' => [
                                            'bg' => '#93c5fd',
                                            'text' => '#1e3a8a',
                                            'border' => '#3b82f6',
                                            'dark_bg' => '#1e40af',
                                            'dark_text' => '#dbeafe',
                                            'dark_border' => '#60a5fa',
                                        ],
                                        'completed' => [
                                            'bg' => '#86efac',
                                            'text' => '#14532d',
                                            'border' => '#4ade80',
                                            'dark_bg' => '#166534',
                                            'dark_text' => '#dcfce7',
                                            'dark_border' => '#16a34a',
                                        ],
                                        'cancelled' => [
                                            'bg' => '#fca5a5',
                                            'text' => '#7f1d1d',
                                            'border' => '#f87171',
                                            'dark_bg' => '#991b1b',
                                            'dark_text' => '#fee2e2',
                                            'dark_border' => '#dc2626',
                                        ],
                                    ];
                                    
                                    $colors = $statusColors[$task['status']] ?? $statusColors['pending'];
                                    $style = "background-color: {$colors['bg']}; color: {$colors['text']}; border-color: {$colors['border']};";
                                    $darkStyle = "background-color: {$colors['dark_bg']}; color: {$colors['dark_text']}; border-color: {$colors['dark_border']};";
                                    
                                    // Calculate position based on grid - simple and direct
                                    $col = $index % 7; // Column (0-6)
                                    $cellWidthPercent = 100 / 7; // 14.2857% per cell
                                    $leftPercent = $col * $cellWidthPercent;
                                    $widthPercent = $span * $cellWidthPercent;
                                    
                                    // Calculate top offset: week row * cell height + task row * task height
                                    // Each cell has minHeight in px, convert to rem (16px = 1rem)
                                    $cellHeightRem = $minHeight / 16; // Convert px to rem
                                    $topOffset = ($weekRow * $cellHeightRem) + ($row * 2.25); // Week row + task row (smaller spacing for widget)
                                @endphp
                                
                                <div 
                                    class="absolute text-[10px] p-1.5 rounded-md cursor-pointer hover:opacity-90 transition-all border-2 font-medium shadow-sm task-bar-item pointer-events-auto"
                                    style="
                                        left: calc({{ $leftPercent }}% + 0.5rem); 
                                        width: calc({{ $widthPercent }}% - 0.5rem - ({{ $span - 1 }} * 1px)); 
                                        top: {{ $topOffset }}rem; 
                                        z-index: {{ 10 + $row }};
                                        box-sizing: border-box;
                                        {{ $style }}
                                    "
                                    data-light-style="{{ $style }}"
                                    data-dark-style="{{ $darkStyle }}"
                                    data-task-status="{{ $task['status'] }}"
                                    title="{{ $task['title'] }} ({{ $task['start'] }} - {{ $task['end'] }}) | {{ implode(', ', $task['employees']) }}"
                                    onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                >
                                    <div class="font-semibold truncate mb-0.5">{{ $task['title'] }}</div>
                                    @if(!empty($task['employees']))
                                        <div class="text-[9px] opacity-90 mt-0.5 truncate">
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
            <div class="flex items-center gap-4 text-xs pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded border-2" style="background-color: #fde68a; border-color: #fbbf24;"></div>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">Pending</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded border-2" style="background-color: #93c5fd; border-color: #3b82f6;"></div>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">In Progress</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded border-2" style="background-color: #86efac; border-color: #4ade80;"></div>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">Completed</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded border-2" style="background-color: #fca5a5; border-color: #f87171;"></div>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">Cancelled</span>
                </div>
            </div>
        </div>
    </x-filament::section>

    <script>
        // Update task bar colors based on dark mode
        function updateTaskBarColors() {
            const isDark = document.documentElement.classList.contains('dark');
            const taskBars = document.querySelectorAll('.task-bar-item');
            
            taskBars.forEach(bar => {
                const lightStyle = bar.dataset.lightStyle || '';
                const darkStyle = bar.dataset.darkStyle || '';
                
                // Get base style (position, size, etc.) - remove color-related styles
                const currentStyle = bar.getAttribute('style') || '';
                const baseStyle = currentStyle
                    .split(';')
                    .filter(s => {
                        const trimmed = s.trim();
                        return trimmed && 
                               !trimmed.includes('background-color') && 
                               !trimmed.includes('color:') && 
                               !trimmed.includes('border-color');
                    })
                    .join(';');
                
                // Apply appropriate style
                if (isDark && darkStyle) {
                    bar.setAttribute('style', baseStyle + (baseStyle ? '; ' : '') + darkStyle);
                } else if (lightStyle) {
                    bar.setAttribute('style', baseStyle + (baseStyle ? '; ' : '') + lightStyle);
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
        
        // Also update immediately if DOM is already loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateTaskBarColors);
        } else {
            updateTaskBarColors();
        }
    </script>
</x-filament-widgets::widget>
