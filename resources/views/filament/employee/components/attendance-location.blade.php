@php
    $officeLat = config('attendance.office_latitude');
    $officeLng = config('attendance.office_longitude');
    $radius = app(\App\Services\AttendanceLocationService::class)->radiusMeters();
@endphp

<div
    id="attendance-location-panel"
    data-office-lat="{{ $officeLat }}"
    data-office-lng="{{ $officeLng }}"
    data-radius="{{ $radius }}"
    class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
>
    <div class="mb-4 flex items-center gap-3">
        <x-filament::icon icon="heroicon-o-map-pin" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Status Lokasi GPS</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Klik tombol di bawah — browser akan meminta izin lokasi.
            </p>
        </div>
    </div>

    <div class="space-y-4">
        <div id="location-idle">
            <x-filament::button
                type="button"
                id="btn-capture-location"
                icon="heroicon-o-map-pin"
            >
                Izinkan &amp; Ambil Lokasi GPS
            </x-filament::button>
        </div>

        <div id="location-loading" class="hidden flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            <span>Meminta izin lokasi dan mengambil koordinat...</span>
        </div>

        <div id="location-error" class="hidden rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-950/30 dark:ring-danger-400/30">
            <p id="location-error-text" class="text-sm font-medium text-danger-700 dark:text-danger-400"></p>
            <div class="mt-3">
                <x-filament::button type="button" id="btn-retry-location" size="sm" color="gray">
                    Coba lagi
                </x-filament::button>
            </div>
        </div>

        <div id="location-success" class="hidden rounded-lg bg-success-50 p-4 ring-1 ring-success-600/20 dark:bg-success-950/20 dark:ring-success-400/30">
            <p class="text-sm font-medium text-success-700 dark:text-success-400">Lokasi berhasil diambil</p>
            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    Koordinat:
                    <span id="location-coords" class="font-mono font-medium text-gray-950 dark:text-white">-</span>
                </p>
                <p>
                    Jarak dari kantor:
                    <span id="location-distance" class="font-medium text-gray-950 dark:text-white">-</span>
                    <span class="text-gray-500 dark:text-gray-400">(radius {{ $radius }} m)</span>
                </p>
            </div>
            <p id="location-outside-warning" class="mt-2 hidden text-sm font-medium text-warning-600 dark:text-warning-400">
                Di luar wilayah absen — tetap bisa kirim, perlu verifikasi admin.
            </p>
            <p id="location-inside-note" class="mt-2 hidden text-sm font-medium text-success-600 dark:text-success-400">
                Dalam radius wilayah absen.
            </p>
            <div class="mt-3">
                <x-filament::button type="button" id="btn-refresh-location" size="sm" color="gray" outlined>
                    Refresh lokasi
                </x-filament::button>
            </div>
        </div>
    </div>
</div>
