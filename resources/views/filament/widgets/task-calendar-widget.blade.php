<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Kalender Pekerjaan</span>
                <a 
                    href="{{ \App\Filament\Resources\TaskResource::getUrl('index') }}"
                    class="text-sm text-primary-600 hover:text-primary-700"
                >
                    Lihat Semua →
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
                        class="px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
                    >
                        ←
                    </button>
                    <h3 class="text-sm font-semibold">
                        {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                    </h3>
                    <button 
                        type="button"
                        wire:click="nextMonth"
                        class="px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50"
                    >
                        →
                    </button>
                    <button 
                        type="button"
                        wire:click="goToToday"
                        class="px-2 py-1 text-xs font-medium text-white bg-primary-600 border border-transparent rounded hover:bg-primary-700"
                    >
                        Hari Ini
                    </button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="grid grid-cols-7 gap-px bg-gray-200">
                    <!-- Day Headers -->
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                        <div class="bg-gray-50 p-1 text-center text-xs font-medium text-gray-700">
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
                        <div class="min-h-[60px] bg-white p-0.5 border-r border-b border-gray-100 {{ $currentDate->month != $this->currentMonth ? 'bg-gray-50' : '' }}">
                            <div class="text-xs font-medium mb-0.5 {{ $currentDate->month != $this->currentMonth ? 'text-gray-400' : ($currentDate->isToday() ? 'text-primary-600 font-bold' : 'text-gray-700') }}">
                                {{ $currentDate->day }}
                            </div>
                            <div class="space-y-0.5">
                                @foreach($this->tasks as $task)
                                    @php
                                        $taskStart = \Carbon\Carbon::parse($task['start']);
                                        $taskEnd = \Carbon\Carbon::parse($task['end']);
                                        $isInRange = $currentDate->between($taskStart, $taskEnd);
                                    @endphp
                                    @if($isInRange)
                                        <div 
                                            class="text-[10px] p-0.5 rounded cursor-pointer hover:opacity-80 truncate {{ 
                                                $task['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                ($task['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                                ($task['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))
                                            }}"
                                            title="{{ $task['title'] }} - {{ implode(', ', array_slice($task['employees'], 0, 2)) }}"
                                            onclick="window.location.href='{{ \App\Filament\Resources\TaskResource::getUrl('edit', ['record' => $task['id']]) }}'"
                                        >
                                            {{ Str::limit($task['title'], 15) }}
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
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-yellow-100 rounded"></div>
                    <span>Pending</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-blue-100 rounded"></div>
                    <span>Progress</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 bg-green-100 rounded"></div>
                    <span>Selesai</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
