@php
    $photos = $photos ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($photos) > 0)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($photos as $photo)
            <div class="relative">
                <img 
                    src="{{ \Storage::disk($disk)->url($photo) }}" 
                    alt="Foto Bukti Kerja"
                    class="w-full h-auto rounded-lg shadow-md object-cover"
                    style="max-height: 200px;"
                />
            </div>
        @endforeach
    </div>
@else
    <p class="text-gray-500">Tidak ada foto</p>
@endif

