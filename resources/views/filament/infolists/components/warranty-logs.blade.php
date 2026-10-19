@php
    $logs = $logs ?? collect([]);
@endphp

@if($logs->isEmpty())
    <p class="text-gray-500 dark:text-gray-400">Belum ada log garansi/service.</p>
@else
    <div class="space-y-4">
        @foreach($logs as $log)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-start mb-2 gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            oleh {{ $log->changedBy?->name ?? 'System / Client' }}
                            · {{ $log->actionLabel() }}
                        </p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded
                        @if($log->status === 'pending') bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                        @elseif($log->status === 'in_progress') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                        @elseif($log->status === 'completed') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                        @else bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                        @endif">
                        {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                    </span>
                </div>

                @if($log->action_type === 'replace' && $log->component_type)
                    <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                        <p class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                            Ganti Part
                        </p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <p class="text-gray-600 dark:text-gray-400">Jenis</p>
                                <p class="font-medium">{{ \App\Services\WarrantyClaimService::COMPONENT_TYPES[$log->component_type] ?? $log->component_type }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600 dark:text-gray-400">SN Lama → damaged</p>
                                <p class="font-medium">{{ $log->old_component_sn ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600 dark:text-gray-400">SN Baru</p>
                                <p class="font-medium text-green-700 dark:text-green-300">{{ $log->new_component_sn ?: '-' }}</p>
                            </div>
                        </div>
                    </div>
                @elseif($log->action_type === 'service' && $log->component_type)
                    <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-200 dark:border-amber-800">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-1">
                            Service Part (tanpa ganti stok)
                        </p>
                        <p class="text-sm">
                            {{ \App\Services\WarrantyClaimService::COMPONENT_TYPES[$log->component_type] ?? $log->component_type }}
                            @if($log->old_component_sn)
                                — SN: {{ $log->old_component_sn }}
                            @endif
                        </p>
                    </div>
                @endif

                @if($log->notes)
                    <div class="mt-2">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Catatan</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $log->notes }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
