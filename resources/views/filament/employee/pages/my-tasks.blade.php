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
            <button 
                type="button"
                wire:click="$set('showCreateModal', true)"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700"
            >
                + Tambah Pekerjaan Saya
            </button>
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
                                        title="{{ $task['title'] }}"
                                        wire:click="openTask('{{ $task['id'] }}')"
                                    >
                                        <div class="font-medium truncate">{{ $task['title'] }}</div>
                                        @if(!empty($task['proof_images']))
                                            <div class="text-xs opacity-75">✓ Bukti terupload</div>
                                        @endif
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

    <!-- Task Detail & Upload Modal -->
    @if($showUploadModal && $selectedTask)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">{{ $selectedTask->title }}</h3>
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
                                    $selectedTask->status == 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($selectedTask->status == 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                    ($selectedTask->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))
                                }}">
                                    {{ ucfirst(str_replace('_', ' ', $selectedTask->status)) }}
                                </span>
                            </div>
                        </div>
                        <form wire:submit.prevent="uploadProof">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Upload Foto Bukti Pekerjaan
                                    </label>
                                    <input 
                                        type="file" 
                                        wire:model="proofImages" 
                                        multiple 
                                        accept="image/*"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
                                    >
                                    @error('proofImages') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                @if(!empty($selectedTask->proof_images))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Foto Bukti yang Sudah Terupload
                                        </label>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($selectedTask->proof_images as $image)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3_public')->url($image) }}" alt="Proof" class="w-full h-24 object-cover rounded">
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Catatan (Opsional)
                                    </label>
                                    <textarea 
                                        wire:model="notes" 
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        placeholder="Tambahkan catatan tentang pekerjaan ini..."
                                    ></textarea>
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button 
                                    type="button"
                                    wire:click="closeModal"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:col-start-2 sm:text-sm"
                                >
                                    Tutup
                                </button>
                                <button 
                                    type="submit"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 sm:mt-0 sm:col-start-1 sm:text-sm"
                                >
                                    Simpan Bukti
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
