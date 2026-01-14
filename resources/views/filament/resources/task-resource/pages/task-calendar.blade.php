<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button 
                    type="button"
                    wire:click="previousMonth"
                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    ← Sebelumnya
                </button>
                <h2 class="text-xl font-semibold">
                    {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                </h2>
                <button 
                    type="button"
                    wire:click="nextMonth"
                    class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                >
                    Selanjutnya →
                </button>
                <button 
                    type="button"
                    wire:click="goToToday"
                    class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
                >
                    Hari Ini
                </button>
            </div>
            <a 
                href="{{ \App\Filament\Resources\TaskResource::getUrl('create') }}"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
            >
                + Tambah Pekerjaan
            </a>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="grid grid-cols-7 gap-px bg-gray-200">
                <!-- Day Headers -->
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                    <div class="bg-gray-50 p-2 text-center text-sm font-medium text-gray-700">
                        {{ $day }}
                    </div>
                @endforeach

                <!-- Calendar Days -->
                @php
                    $startOfMonth = \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1);
                    $endOfMonth = $startOfMonth->copy()->endOfMonth();
                    $startDate = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $endDate = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                    $currentDate = $startDate->copy();
                @endphp

                @while($currentDate <= $endDate)
                    <div class="min-h-[100px] bg-white p-1 border-r border-b border-gray-100 {{ $currentDate->month != $this->currentMonth ? 'bg-gray-50' : '' }}">
                        <div class="text-xs font-medium mb-1 {{ $currentDate->month != $this->currentMonth ? 'text-gray-400' : ($currentDate->isToday() ? 'text-primary-600 font-bold' : 'text-gray-700') }}">
                            {{ $currentDate->day }}
                        </div>
                        <div class="space-y-1">
                            @foreach($this->tasks as $task)
                                @php
                                    $taskStart = \Carbon\Carbon::parse($task['start']);
                                    $taskEnd = \Carbon\Carbon::parse($task['end']);
                                    $isInRange = $currentDate->between($taskStart, $taskEnd);
                                @endphp
                                @if($isInRange)
                                    <div 
                                        class="text-xs p-1 rounded cursor-pointer hover:opacity-80 {{ 
                                            $task['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                            ($task['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                            ($task['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))
                                        }}"
                                        title="{{ $task['title'] }} - {{ implode(', ', $task['employees']) }}"
                                        onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                    >
                                        <div class="font-medium truncate">{{ $task['title'] }}</div>
                                        <div class="text-xs opacity-75">{{ implode(', ', array_slice($task['employees'], 0, 2)) }}{{ count($task['employees']) > 2 ? '...' : '' }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @php $currentDate->addDay(); @endphp
                @endwhile
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-yellow-100 rounded"></div>
                <span>Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-100 rounded"></div>
                <span>In Progress</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-100 rounded"></div>
                <span>Completed</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-100 rounded"></div>
                <span>Cancelled</span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
