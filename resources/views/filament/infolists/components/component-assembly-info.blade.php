@php
    $assemblies = $assemblies ?? collect([]);
    $componentSn = $componentSn ?? '';
    
    // Ensure assemblies is a collection
    if (!($assemblies instanceof \Illuminate\Support\Collection)) {
        $assemblies = collect($assemblies ?? []);
    }
@endphp

@if($assemblies->isEmpty())
    <p class="text-gray-500 dark:text-gray-400">Komponen ini tidak digunakan di assembly manapun.</p>
@else
    <div class="space-y-4">
        @foreach($assemblies as $assembly)
            @if($assembly)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Serial Number Assembly:</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $assembly->serial_number ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Invoice:</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                @if(isset($assembly->invoice) && $assembly->invoice)
                                    {{ $assembly->invoice->invoice_number }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Produk:</p>
                            <p class="text-base text-gray-900 dark:text-gray-100">
                                @if(isset($assembly->product_type))
                                    @if($assembly->product_type === 'Macan')
                                        DigiGate Macan (i7 11700K)
                                    @elseif($assembly->product_type === 'Maleo')
                                        DigiGate Maleo (i7 8700K)
                                    @elseif($assembly->product_type === 'Komodo')
                                        DigiGate Komodo (i7 14700K)
                                    @else
                                        {{ $assembly->product_type }}
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Assembly:</p>
                            <p class="text-base text-gray-900 dark:text-gray-100">
                                @if(isset($assembly->assembly_date) && $assembly->assembly_date)
                                    @if($assembly->assembly_date instanceof \Carbon\Carbon)
                                        {{ $assembly->assembly_date->format('d/m/Y') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($assembly->assembly_date)->format('d/m/Y') }}
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Posisi di Assembly:</p>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $snDetails = $assembly->sn_details ?? [];
                                    $positions = [];
                                    if (is_array($snDetails)) {
                                        foreach($snDetails as $key => $value) {
                                            if ($value === $componentSn) {
                                                $positions[] = match($key) {
                                                    'chassis' => 'Chassis',
                                                    'processor' => 'Processor',
                                                    'ram_1' => 'RAM Slot 1',
                                                    'ram_2' => 'RAM Slot 2',
                                                    'ssd' => 'SSD',
                                                    default => ucfirst($key),
                                                };
                                            }
                                        }
                                    }
                                @endphp
                                @if(count($positions) > 0)
                                    @foreach($positions as $position)
                                        <span class="px-2 py-1 text-xs rounded bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                            {{ $position }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-gray-400 text-sm">Tidak ditemukan</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endif
