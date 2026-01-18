@php
    $photos = $photos ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($photos) > 0)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($photos as $photo)
            <div class="relative">
                <a href="{{ \Storage::disk($disk)->url($photo) }}" target="_blank" class="block">
                    <img 
                        src="{{ \Storage::disk($disk)->url($photo) }}" 
                        alt="Foto Bukti Kerja"
                        class="w-full h-auto rounded-lg shadow-md object-cover cursor-pointer hover:shadow-lg transition-shadow"
                        style="max-height: 200px;"
                    />
                </a>
            </div>
        @endforeach
    </div>
@else
    <p class="text-gray-500 dark:text-gray-400">Tidak ada foto</p>
@endif

