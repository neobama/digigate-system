@php
    $proofFiles = $proof_files ?? [];
    $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
@endphp

@if(count($proofFiles) > 0)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($proofFiles as $file)
            <div class="relative group">
                @php
                    $isVideo = in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                @endphp
                
                @if($isVideo)
                    <a href="{{ \Storage::disk($disk)->url($file) }}" target="_blank" class="block">
                        <div class="relative w-full aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                            <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l11.032-5.925a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                            </svg>
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-opacity rounded-lg"></div>
                        </div>
                    </a>
                @else
                    <a href="{{ \Storage::disk($disk)->url($file) }}" target="_blank" class="block">
                        <img 
                            src="{{ \Storage::disk($disk)->url($file) }}" 
                            alt="Bukti Kendala"
                            class="w-full h-auto rounded-lg shadow-md object-cover cursor-pointer hover:shadow-lg transition-shadow"
                            style="max-height: 200px;"
                        />
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@else
    <p class="text-gray-500 dark:text-gray-400">Tidak ada bukti video/foto</p>
@endif
