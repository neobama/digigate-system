<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button 
                    type="button"
                    wire:click="previousMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    ←
                </button>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 px-4">
                    {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                </h2>
                <button 
                    type="button"
                    wire:click="nextMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    →
                </button>
                <button 
                    type="button"
                    wire:click="goToToday"
                    class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm ml-2"
                >
                    Hari Ini
                </button>
            </div>
            <button 
                type="button"
                wire:click="$set('showCreateModal', true)"
                class="px-5 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm"
            >
                + Tambah Pekerjaan Saya
            </button>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-lg relative">
            <div class="grid grid-cols-7" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
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
                    $minHeight = max(200, 80 + ($maxTasksPerDay * 48)); // Base height + task height in px
                @endphp

                @foreach($days as $index => $day)
                    <div 
                        class="bg-white dark:bg-gray-800 p-3 border-r border-b border-gray-200 dark:border-gray-700 relative {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-900' : '' }}" 
                        style="min-height: {{ $minHeight }}px;"
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
                                title="{{ $task['title'] }} ({{ $task['start'] }} - {{ $task['end'] }})"
                                wire:click="openTask('{{ $task['id'] }}')"
                            >
                                <div class="font-semibold truncate mb-0.5">{{ $task['title'] }}</div>
                                @if(!empty($task['proof_images']))
                                    <div class="text-[10px] opacity-90 mt-0.5">✓ Bukti terupload</div>
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

    <!-- Task Detail & Upload Modal -->
    @if($showUploadModal && $selectedTask)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $selectedTask->title }}</h3>
                        <div class="space-y-2 mb-4">
                            <div>
                                <strong>Tanggal:</strong> {{ $selectedTask->start_date->format('d/m/Y') }} - {{ $selectedTask->end_date->format('d/m/Y') }}
                            </div>
                            @if($selectedTask->description)
                                <div>
                                    <strong>Deskripsi:</strong> {{ $selectedTask->description }}
                                </div>
                            @endif
                            <div>
                                <strong>Status:</strong> 
                                <span class="px-2 py-1 text-xs rounded {{ 
                                    $selectedTask->status == 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 
                                    ($selectedTask->status == 'in_progress' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 
                                    ($selectedTask->status == 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100'))
                                }}">
                                    {{ ucfirst(str_replace('_', ' ', $selectedTask->status)) }}
                                </span>
                            </div>
                            <div>
                                <strong>Karyawan yang Ditugaskan:</strong> 
                                <div class="flex flex-wrap gap-2 mt-1">
                                    @foreach($selectedTask->employees as $employee)
                                        <span class="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            {{ $employee->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add Employee Section (only for self-assigned tasks) -->
                        @if($this->canManageEmployees())
                            <div class="mb-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Tambah Karyawan Lain
                                    </h4>
                                    <button 
                                        type="button"
                                        wire:click="$set('showAddEmployeeSection', !$showAddEmployeeSection)"
                                        class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
                                    >
                                        {{ $showAddEmployeeSection ? 'Sembunyikan' : 'Tampilkan' }}
                                    </button>
                                </div>
                                
                                @if($showAddEmployeeSection)
                                    <div class="space-y-2">
                                        @php
                                            $availableEmployees = $this->getAvailableEmployees();
                                        @endphp
                                        
                                        @if($availableEmployees->count() > 0)
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Pilih Karyawan
                                            </label>
                                            <select 
                                                wire:model="selectedEmployees"
                                                multiple
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                                size="5"
                                            >
                                                @foreach($availableEmployees as $employee)
                                                    <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->position }})</option>
                                                @endforeach
                                            </select>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Tekan Ctrl/Cmd untuk memilih beberapa karyawan
                                            </p>
                                            <button 
                                                type="button"
                                                wire:click="addEmployees"
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 dark:bg-primary-500 text-sm font-medium text-white hover:bg-primary-700 dark:hover:bg-primary-600"
                                            >
                                                Tambahkan Karyawan
                                            </button>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Semua karyawan aktif sudah ditambahkan ke task ini.
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                        
                        <!-- Status Update Buttons -->
                        @if($selectedTask->status === 'pending')
                            <div class="mb-4">
                                <button 
                                    type="button"
                                    wire:click="updateStatus('in_progress')"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 dark:bg-blue-500 text-base font-medium text-white hover:bg-blue-700 dark:hover:bg-blue-600 sm:text-sm"
                                >
                                    Mulai Pekerjaan (Ubah ke In Progress)
                                </button>
                            </div>
                        @endif
                        
                        <form wire:submit.prevent="uploadProof">
                            {{ $this->form }}
                            
                            @php
                                $currentEmployee = auth()->user()->employee;
                                $employeeProofImages = [];
                                if ($currentEmployee) {
                                    $pivotData = $selectedTask->employees->firstWhere('id', $currentEmployee->id);
                                    $employeeProofImages = $pivotData->pivot->proof_images ?? [];
                                }
                            @endphp
                            
                            @if(!empty($employeeProofImages))
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Foto Bukti yang Sudah Terupload (Anda)
                                    </label>
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach($employeeProofImages as $image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($image) }}" alt="Proof" class="w-full h-24 object-cover rounded">
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Show proof submission status for all employees -->
                            @if($selectedTask->employees->count() > 1)
                                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status Submit Bukti (Semua Karyawan)
                                    </label>
                                    <div class="space-y-2">
                                        @foreach($selectedTask->employees as $employee)
                                            @php
                                                $hasProof = !empty($employee->pivot->proof_images) && is_array($employee->pivot->proof_images) && count($employee->pivot->proof_images) > 0;
                                            @endphp
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $employee->name }}</span>
                                                @if($hasProof)
                                                    <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                        ✓ Sudah Submit
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                        ⏳ Belum Submit
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Task akan otomatis menjadi Completed setelah semua karyawan submit bukti.
                                    </p>
                                </div>
                            @endif
                            
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button 
                                    type="button"
                                    wire:click="closeModal"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-700 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:col-start-2 sm:text-sm"
                                >
                                    Tutup
                                </button>
                                <button 
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="uploadProof"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 dark:bg-primary-500 text-base font-medium text-white hover:bg-primary-700 dark:hover:bg-primary-600 sm:mt-0 sm:col-start-1 sm:text-sm disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="uploadProof">Simpan Bukti</span>
                                    <span wire:loading wire:target="uploadProof">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Create Task Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeCreateModal"></div>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Tambah Pekerjaan Baru</h3>
                        <form wire:submit.prevent="createTask">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Judul Pekerjaan <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model="newTaskTitle" 
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        placeholder="Masukkan judul pekerjaan"
                                    >
                                    @error('newTaskTitle') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Deskripsi
                                    </label>
                                    <textarea 
                                        wire:model="newTaskDescription" 
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        placeholder="Masukkan deskripsi pekerjaan (opsional)"
                                    ></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Tanggal Mulai <span class="text-red-500">*</span>
                                        </label>
                                        <input 
                                            type="date" 
                                            wire:model="newTaskStartDate" 
                                            wire:change="updatedNewTaskStartDate"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        >
                                        @error('newTaskStartDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Tanggal Selesai <span class="text-red-500">*</span>
                                        </label>
                                        <input 
                                            type="date" 
                                            wire:model="newTaskEndDate" 
                                            wire:change="updatedNewTaskEndDate"
                                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        >
                                        @error('newTaskEndDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                @if($newTaskStartDate === $newTaskEndDate && $newTaskStartDate)
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Jam Mulai <span class="text-red-500">*</span>
                                            </label>
                                            <input 
                                                type="time" 
                                                wire:model="newTaskStartTime" 
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                            >
                                            @error('newTaskStartTime') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Jam Selesai <span class="text-red-500">*</span>
                                            </label>
                                            <input 
                                                type="time" 
                                                wire:model="newTaskEndTime" 
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                            >
                                            @error('newTaskEndTime') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        Assign ke Karyawan Lain (Opsional)
                                    </label>
                                    @php
                                        $availableEmployees = $this->getAvailableEmployeesForNewTask();
                                    @endphp
                                    
                                    @if($availableEmployees->count() > 0)
                                        <div class="fi-fo-field-wrp-label">
                                            <div class="rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                                                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-60 overflow-y-auto">
                                                    @foreach($availableEmployees as $emp)
                                                        <label class="fi-fo-checkbox-list-option flex cursor-pointer items-center gap-x-3 px-4 py-3 outline-none transition duration-75 hover:bg-gray-50 dark:hover:bg-gray-700/50 has-[:focus]:bg-gray-50 dark:has-[:focus]:bg-gray-700/50">
                                                            <input 
                                                                type="checkbox"
                                                                wire:model="newTaskEmployees"
                                                                value="{{ $emp->id }}"
                                                                class="fi-checkbox-input h-4 w-4 rounded border-gray-300 text-primary-600 shadow-sm transition duration-75 checked:border-primary-500 checked:bg-primary-500 focus:ring-2 focus:ring-primary-500 focus:ring-offset-0 disabled:bg-gray-50 disabled:text-gray-50 disabled:checked:bg-current disabled:checked:text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:disabled:bg-transparent dark:disabled:checked:bg-gray-600"
                                                            >
                                                            <span class="text-sm leading-6 text-gray-700 dark:text-gray-300">
                                                                <span class="font-medium">{{ $emp->name }}</span>
                                                                <span class="text-gray-500 dark:text-gray-400"> • {{ $emp->position }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                            Anda akan otomatis ditambahkan sebagai assignee.
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Tidak ada karyawan lain yang tersedia.
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button 
                                    type="button"
                                    wire:click="closeCreateModal"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-700 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:col-start-2 sm:text-sm"
                                >
                                    Batal
                                </button>
                                <button 
                                    type="submit"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 dark:bg-primary-500 text-base font-medium text-white hover:bg-primary-700 dark:hover:bg-primary-600 sm:mt-0 sm:col-start-1 sm:text-sm"
                                >
                                    Tambah Pekerjaan
                                </button>
                            </div>
                        </form>
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
