@php
    $photo = $photo ?? null;
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if($photo)
    <div class="flex justify-center">
        <div class="relative max-w-2xl w-full">
            <a href="{{ \Storage::disk($disk)->url($photo) }}" target="_blank" class="block">
                <img
                    src="{{ \Storage::disk($disk)->url($photo) }}"
                    alt="Foto Absensi"
                    class="w-full h-auto rounded-lg shadow-lg object-contain border border-gray-200 dark:border-gray-700"
                    style="max-height: 600px;"
                />
            </a>
            <div class="mt-2 text-center">
                <a
                    href="{{ \Storage::disk($disk)->url($photo) }}"
                    target="_blank"
                    class="text-primary-600 hover:text-primary-700 underline text-sm"
                >
                    Buka di tab baru / Download
                </a>
            </div>
        </div>
    </div>
@else
    <p class="text-gray-500 text-center">Tidak ada foto absensi</p>
@endif
