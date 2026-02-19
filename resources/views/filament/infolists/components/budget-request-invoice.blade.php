@php
    $invoice = $invoice ?? null;
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if($invoice)
    @php
        $isPdf = str_contains(strtolower($invoice), '.pdf');
        $url = \Illuminate\Support\Facades\Storage::disk($disk)->url($invoice);
    @endphp
    
    <div class="space-y-2">
        @if($isPdf)
            <a href="{{ $url }}" target="_blank" class="inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span>Lihat Invoice PDF</span>
            </a>
        @else
            <a href="{{ $url }}" target="_blank" class="block">
                <img 
                    src="{{ $url }}" 
                    alt="Invoice"
                    class="max-w-full h-auto rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow"
                    style="max-height: 500px;"
                />
            </a>
        @endif
    </div>
@else
    <p class="text-gray-500 dark:text-gray-400">Tidak ada invoice</p>
@endif
