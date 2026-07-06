@php
    $locationService = app(\App\Services\AttendanceLocationService::class);
    $officeLat = $locationService->officeLatitude();
    $officeLng = $locationService->officeLongitude();
    $radius = $locationService->radiusMeters();
@endphp

<div
    id="attendance-camera-panel"
    wire:ignore
    data-office-lat="{{ $officeLat }}"
    data-office-lng="{{ $officeLng }}"
    data-radius="{{ $radius }}"
    class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4"
>
    <div class="flex items-center gap-3">
        <x-filament::icon icon="heroicon-o-camera" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Selfie Absen</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Buka kamera lalu ambil foto. Izin kamera &amp; GPS diminta otomatis saat foto diambil.
            </p>
        </div>
    </div>

    <div id="camera-idle">
        <x-filament::button type="button" id="btn-start-camera" icon="heroicon-o-camera">
            Buka Kamera untuk Selfie
        </x-filament::button>
    </div>

    <div id="camera-loading" class="hidden flex items-center justify-center gap-2 rounded-xl bg-white p-8 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        <x-filament::loading-indicator class="h-4 w-4" />
        <span id="camera-loading-text">Membuka kamera...</span>
    </div>

    <div id="camera-error" class="hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-danger-600/30 dark:bg-white/5 dark:ring-danger-400/40">
        <p id="camera-error-text" class="text-sm font-medium text-danger-600 dark:text-danger-400"></p>
        <div class="mt-3">
            <x-filament::button type="button" id="btn-retry-camera" size="sm" color="gray">
                Coba lagi
            </x-filament::button>
        </div>
    </div>

    <div id="camera-active" class="hidden space-y-3">
        <div class="overflow-hidden rounded-xl bg-black ring-1 ring-gray-950/5 dark:ring-white/10">
            <video
                id="attendance-camera-video"
                playsinline
                muted
                class="mx-auto w-full max-w-md"
                style="transform: scaleX(-1); max-height: 400px; object-fit: cover;"
            ></video>
        </div>
        <x-filament::button type="button" id="btn-capture-photo" icon="heroicon-o-camera">
            Ambil Foto Selfie
        </x-filament::button>
    </div>

    <div id="camera-preview" class="hidden space-y-4">
        <div class="overflow-hidden rounded-xl ring-1 ring-success-600/30 dark:ring-success-400/30">
            <img id="attendance-camera-preview-img" alt="Preview selfie" class="mx-auto w-full max-w-md object-cover" style="max-height: 400px;" />
        </div>
        <p class="text-sm font-medium text-success-600 dark:text-success-400">Foto berhasil diambil. Periksa lokasi di bawah sebelum kirim absensi.</p>

        <div id="location-loading" class="hidden flex items-center gap-2 rounded-xl bg-white p-4 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
            <x-filament::loading-indicator class="h-4 w-4" />
            <span>Mengambil lokasi GPS...</span>
        </div>

        <div id="location-summary" class="hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="mb-3 flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Lokasi saat absen</p>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">Koordinat</dt>
                    <dd id="location-coords" class="font-mono font-medium text-gray-950 dark:text-white">-</dd>
                </div>
                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">Jarak dari kantor</dt>
                    <dd class="text-gray-950 dark:text-white">
                        <span id="location-distance" class="font-medium">-</span>
                        <span class="text-gray-500 dark:text-gray-400"> (radius {{ $radius }} m)</span>
                    </dd>
                </div>
            </dl>
            <div id="location-inside-note" class="mt-3 hidden">
                <x-filament::badge color="success" icon="heroicon-o-check-circle">
                    Dalam radius wilayah absen
                </x-filament::badge>
            </div>
            <div id="location-outside-warning" class="mt-3 hidden">
                <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
                    Di luar wilayah absen — perlu verifikasi admin
                </x-filament::badge>
            </div>
        </div>

        <div id="location-error" class="hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-danger-600/30 dark:bg-white/5 dark:ring-danger-400/40">
            <p id="location-error-text" class="text-sm font-medium text-danger-600 dark:text-danger-400"></p>
            <div class="mt-3">
                <x-filament::button type="button" id="btn-retry-location" size="sm" color="gray">
                    Ambil ulang lokasi
                </x-filament::button>
            </div>
        </div>

        <x-filament::button type="button" id="btn-retake-photo" size="sm" color="gray" outlined>
            Ulangi Foto
        </x-filament::button>
    </div>

    <canvas id="attendance-camera-canvas" class="hidden"></canvas>
</div>
