@php
    $proof = $proof ?? null;
    $disk = env('FILESYSTEM_DISK') === 's3' ? 's3_public' : 'public';
@endphp

@if($proof)
    <div class="flex justify-center">
        <div class="relative max-w-2xl">
            <a href="{{ \Storage::disk($disk)->url($proof) }}" target="_blank" class="block">
                <img 
                    src="{{ \Storage::disk($disk)->url($proof) }}" 
                    alt="Bukti Pembayaran"
                    class="w-full h-auto rounded-lg shadow-lg object-contain border border-gray-200"
                    style="max-height: 600px;"
                />
            </a>
            <div class="mt-2 text-center">
                <a 
                    href="{{ \Storage::disk($disk)->url($proof) }}" 
                    target="_blank"
                    class="text-primary-600 hover:text-primary-700 underline text-sm"
                >
                    Buka di tab baru / Download
                </a>
            </div>
        </div>
    </div>
@else
    <p class="text-gray-500 text-center">Tidak ada bukti pembayaran</p>
@endif

