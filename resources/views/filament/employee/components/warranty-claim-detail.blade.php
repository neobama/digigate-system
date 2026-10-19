@php
    use App\Services\WarrantyClaimService;
@endphp

<div class="space-y-4 text-sm">
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <p class="text-gray-500">Client</p>
            <p class="font-medium">{{ $record->client_name ?: '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Telepon</p>
            <p class="font-medium">{{ $record->phone_number ?: '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Invoice</p>
            <p class="font-medium">{{ $record->invoice_number ?: '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Tanggal Invoice</p>
            <p class="font-medium">{{ $record->invoice_date?->format('d/m/Y') ?: '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Kategori</p>
            <p class="font-medium">{{ $record->claimCategoryLabel() }}</p>
        </div>
        <div>
            <p class="text-gray-500">Perangkat</p>
            <p class="font-medium">{{ WarrantyClaimService::DEVICE_TYPES[$record->device_type] ?? ($record->device_type ?: '-') }}</p>
        </div>
        <div>
            <p class="text-gray-500">Serial Number</p>
            <p class="font-medium">{{ $record->assembly_serial_number }}</p>
        </div>
        <div>
            <p class="text-gray-500">Link Assembly</p>
            <p class="font-medium">{{ $record->is_assembly_linked ? 'Terhubung' : 'Belum terhubung' }}</p>
        </div>
    </div>

    <div>
        <p class="text-gray-500">Keluhan</p>
        <p class="mt-1 whitespace-pre-wrap">{{ $record->complaint ?: '-' }}</p>
    </div>

    <div>
        <p class="mb-2 font-semibold">Log</p>
        @include('filament.infolists.components.warranty-logs', ['logs' => $record->logs])
    </div>
</div>
