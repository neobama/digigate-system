@php
    /** @var array<int, string>|null $proofs */
    $proofs = $proofs ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($proofs) > 0)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach($proofs as $path)
            <div class="rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                <a href="{{ \Illuminate\Support\Facades\Storage::disk($disk)->url($path) }}" target="_blank" class="block">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk($disk)->url($path) }}"
                        alt="Bukti"
                        class="mx-auto max-h-64 w-full rounded object-contain"
                    />
                </a>
                <p class="mt-2 text-center text-xs text-gray-500">
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk($disk)->url($path) }}" target="_blank" class="text-primary-600 underline">Buka / unduh</a>
                </p>
            </div>
        @endforeach
    </div>
@else
    <p class="text-center text-gray-500">Tidak ada bukti</p>
@endif
