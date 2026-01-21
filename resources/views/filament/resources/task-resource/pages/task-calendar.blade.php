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
                            // Render segments that start at this day index
                            // Each segment is added to tasksByDay at its startIndex
                            $taskSegments = array_filter($tasksByDay[$index] ?? [], function($t) use ($index) {
                                return ($t['startIndex'] ?? $index) === $index;
                            });
                        @endphp
                        @foreach($taskSegments as $taskInfo)
                            @php
                                $task = $taskInfo['task'];
                                $span = $taskInfo['span'];
                                $row = $taskInfo['row'] ?? 0;
                                $startIndex = $taskInfo['startIndex'] ?? $index; // Use startIndex from task, fallback to current index
                                $weekRow = $taskInfo['weekRow'] ?? intval($startIndex / 7); // Use weekRow from segment
                                
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
                                
                                // Calculate position based on startIndex and weekRow
                                $col = $startIndex % 7; // Column (0-6) based on actual start day
                                $cellWidthPercent = 100 / 7; // 14.2857% per cell
                                $leftPercent = $col * $cellWidthPercent;
                                $widthPercent = $span * $cellWidthPercent;
                                
                                // Calculate top offset: week row * cell height + task row * task height
                                // Each cell has minHeight in px, convert to rem (16px = 1rem)
                                // Overlay starts at margin-top: 3.5rem (after header)
                                $cellHeightRem = $minHeight / 16; // Convert px to rem
                                $topOffset = ($weekRow * $cellHeightRem) + ($row * 3); // Week row + task row
                            @endphp
                            
                            <div 
                                class="absolute text-xs p-2 rounded-lg cursor-pointer hover:opacity-90 transition-all border-2 font-medium shadow-sm task-bar-item pointer-events-auto"
                                style="
                                    left: calc({{ $leftPercent }}% + 0.75rem); 
                                    width: calc({{ $widthPercent }}% - 0.75rem - ({{ $span - 1 }} * 1px)); 
                                    top: {{ $topOffset }}rem; 
                                    z-index: {{ 10 + $row }};
                                    box-sizing: border-box;
                                    {{ $style }}
                                "
                                data-light-style="{{ $style }}"
                                data-dark-style="{{ $darkStyle }}"
                                data-task-status="{{ $task['status'] }}"
                                title="{{ $task['title'] }} ({{ $task['start'] }} - {{ $task['end'] }}) | {{ implode(', ', $task['employees']) }}"
                                wire:click="openTask({{ $task['id'] }})"
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
                <div class="w-5 h-5 rounded border-2" style="background-color: #93c5fd; border-color: #3b82f6;"></div>
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

    <!-- Proof Modal -->
    @if($showProofModal && $selectedTask)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showProofModal') }" x-show="show" x-transition style="display: block;">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                Detail Pekerjaan: {{ $selectedTask->title }}
                            </h3>
                            <button wire:click="closeModal" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Task Info -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Tanggal:</span>
                                    <p class="text-gray-900 dark:text-gray-100">
                                        {{ $selectedTask->start_date->format('d/m/Y') }} - {{ $selectedTask->end_date->format('d/m/Y') }}
                                    </p>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                                    <p class="text-gray-900 dark:text-gray-100">
                                        <span class="px-2 py-1 text-xs rounded
                                            @if($selectedTask->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                            @elseif($selectedTask->status === 'in_progress') bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100
                                            @elseif($selectedTask->status === 'completed') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $selectedTask->status)) }}
                                        </span>
                                    </p>
                                </div>
                                @if($selectedTask->description)
                                <div class="col-span-2">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Deskripsi:</span>
                                    <p class="text-gray-900 dark:text-gray-100">{{ $selectedTask->description }}</p>
                                </div>
                                @endif
                            </div>

                            <!-- Progress Status -->
                            @php
                                $allEmployees = $selectedTask->employees;
                                $employeesWithProof = $allEmployees->filter(function ($employee) {
                                    $proofImagesRaw = $employee->pivot->proof_images ?? [];
                                    if (is_string($proofImagesRaw)) {
                                        $proofImages = json_decode($proofImagesRaw, true) ?? [];
                                    } elseif (is_array($proofImagesRaw)) {
                                        $proofImages = $proofImagesRaw;
                                    } else {
                                        $proofImages = [];
                                    }
                                    return !empty($proofImages) && is_array($proofImages) && count($proofImages) > 0;
                                });
                                $progressCount = $employeesWithProof->count();
                                $totalCount = $allEmployees->count();
                                $progressPercentage = $totalCount > 0 ? round(($progressCount / $totalCount) * 100) : 0;
                            @endphp

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">Progress Submit Bukti</h4>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $progressCount }} / {{ $totalCount }} karyawan
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                    <div class="bg-primary-600 h-2.5 rounded-full transition-all" style="width: {{ $progressPercentage }}%"></div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $progressPercentage }}% selesai
                                </p>
                            </div>

                            <!-- Employee Proof Status -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Status Bukti per Karyawan</h4>
                                <div class="space-y-3 max-h-96 overflow-y-auto">
                                    @foreach($selectedTask->employees as $employee)
                                        @php
                                            $proofImagesRaw = $employee->pivot->proof_images ?? [];
                                            if (is_string($proofImagesRaw)) {
                                                $proofImages = json_decode($proofImagesRaw, true) ?? [];
                                            } elseif (is_array($proofImagesRaw)) {
                                                $proofImages = $proofImagesRaw;
                                            } else {
                                                $proofImages = [];
                                            }
                                            $hasProof = !empty($proofImages) && is_array($proofImages) && count($proofImages) > 0;
                                            $proofUploadedAt = $employee->pivot->proof_uploaded_at ?? null;
                                            $notes = $employee->pivot->notes ?? null;
                                        @endphp
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $employee->name }}</span>
                                                @if($hasProof)
                                                    <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                        ✓ Sudah Submit ({{ count($proofImages) }} foto)
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                        ⏳ Belum Submit
                                                    </span>
                                                @endif
                                            </div>

                                            @if($hasProof)
                                                <div class="mt-3">
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                                        Diupload: {{ $proofUploadedAt ? \Carbon\Carbon::parse($proofUploadedAt)->format('d/m/Y H:i') : '-' }}
                                                    </p>
                                                    @if($notes)
                                                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                                            <strong>Catatan:</strong> {{ $notes }}
                                                        </p>
                                                    @endif
                                                    <div class="grid grid-cols-3 gap-2">
                                                        @foreach($proofImages as $image)
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($image) }}" target="_blank" class="block">
                                                                <img 
                                                                    src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($image) }}" 
                                                                    alt="Bukti {{ $employee->name }}"
                                                                    class="w-full h-24 object-cover rounded cursor-pointer hover:opacity-90 transition-opacity"
                                                                />
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <a 
                            href="{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $selectedTask->id]) }}"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Edit Task
                        </a>
                        <button 
                            type="button"
                            wire:click="closeModal"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

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
</x-filament-panels::page>
