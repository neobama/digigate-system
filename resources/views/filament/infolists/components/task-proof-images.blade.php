@php
    $images = $images ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($images) > 0)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($images as $image)
            <div class="relative group">
                <a href="{{ \Storage::disk($disk)->url($image) }}" target="_blank" class="block">
                    <img 
                        src="{{ \Storage::disk($disk)->url($image) }}" 
                        alt="Bukti Pekerjaan"
                        class="w-full h-auto rounded-lg shadow-md object-cover cursor-pointer hover:shadow-lg transition-shadow"
                        style="max-height: 200px;"
                    />
                </a>
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity rounded-lg"></div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-gray-500">Tidak ada foto bukti pekerjaan</p>
@endif
