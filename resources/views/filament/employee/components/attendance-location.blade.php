@php
    $officeLat = config('attendance.office_latitude');
    $officeLng = config('attendance.office_longitude');
    $radius = app(\App\Services\AttendanceLocationService::class)->radiusMeters();
@endphp

<x-filament::section
    icon="heroicon-o-map-pin"
    icon-color="primary"
    heading="Status Lokasi GPS"
    description="Izinkan akses lokasi agar koordinat absensi tercatat. Browser akan meminta izin saat tombol diklik."
>
    <div
        x-data="{
            lat: null,
            lng: null,
            distance: null,
            outside: false,
            loading: false,
            granted: false,
            error: null,
            officeLat: {{ $officeLat }},
            officeLng: {{ $officeLng }},
            radius: {{ $radius }},
            init() {
                Livewire.on('attendance-submitted', () => {
                    this.lat = null;
                    this.lng = null;
                    this.distance = null;
                    this.granted = false;
                    this.error = null;
                });
            },
            syncToLivewire(latitude, longitude) {
                this.lat = latitude;
                this.lng = longitude;
                $wire.set('attendanceFormData.latitude', latitude);
                $wire.set('attendanceFormData.longitude', longitude);
            },
            captureLocation() {
                this.loading = true;
                this.error = null;

                if (!window.isSecureContext) {
                    this.error = 'GPS membutuhkan koneksi HTTPS. Buka situs lewat https:// bukan http://';
                    this.loading = false;
                    return;
                }

                if (!navigator.geolocation) {
                    this.error = 'Browser tidak mendukung GPS. Gunakan Chrome/Safari di HP.';
                    this.loading = false;
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        this.syncToLivewire(latitude, longitude);
                        this.distance = this.haversine(latitude, longitude, this.officeLat, this.officeLng);
                        this.outside = this.distance > this.radius;
                        this.granted = true;
                        this.loading = false;
                    },
                    (err) => {
                        this.granted = false;
                        this.loading = false;
                        this.error = this.formatGeoError(err);
                    },
                    { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
                );
            },
            formatGeoError(err) {
                return {
                    1: 'Izin lokasi ditolak. Buka pengaturan browser/situs lalu izinkan akses lokasi untuk situs ini.',
                    2: 'Lokasi tidak tersedia. Pastikan GPS perangkat aktif.',
                    3: 'Waktu habis saat mengambil lokasi. Coba lagi di area terbuka.',
                }[err.code] || ('Gagal mengambil lokasi GPS: ' + err.message);
            },
            haversine(lat1, lon1, lat2, lon2) {
                const R = 6371000;
                const toRad = (deg) => deg * Math.PI / 180;
                const dLat = toRad(lat2 - lat1);
                const dLon = toRad(lon2 - lon1);
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
                return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
            },
            formatDistance(meters) {
                return new Intl.NumberFormat('id-ID').format(meters) + ' m';
            },
            formatCoord(value) {
                return value != null ? Number(value).toFixed(6) : '-';
            }
        }"
        class="space-y-4"
    >
        <div x-show="!granted && !loading && !error">
            <x-filament::button
                type="button"
                icon="heroicon-o-map-pin"
                x-on:click="captureLocation()"
            >
                Izinkan & Ambil Lokasi GPS
            </x-filament::button>
        </div>

        <div x-show="loading" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            <span>Meminta izin lokasi dan mengambil koordinat...</span>
        </div>

        <x-filament::section
            x-show="error"
            x-cloak
            icon="heroicon-o-exclamation-triangle"
            icon-color="danger"
            heading="Lokasi gagal diambil"
            class="!ring-danger-600/20 dark:!ring-danger-400/30"
        >
            <p class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>
            <div class="mt-3">
                <x-filament::button type="button" size="sm" color="gray" x-on:click="captureLocation()">
                    Coba lagi
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section
            x-show="granted && !error"
            x-cloak
            icon="heroicon-o-check-circle"
            icon-color="success"
            heading="Lokasi berhasil diambil"
        >
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    Koordinat:
                    <span class="font-mono font-medium text-gray-950 dark:text-white">
                        <span x-text="formatCoord(lat)"></span>,
                        <span x-text="formatCoord(lng)"></span>
                    </span>
                </p>
                <p>
                    Jarak dari kantor:
                    <span class="font-medium text-gray-950 dark:text-white" x-text="distance != null ? formatDistance(distance) : '-'"></span>
                    <span class="text-gray-500 dark:text-gray-400">(radius {{ $radius }} m)</span>
                </p>
            </div>

            <div class="mt-3 space-y-2">
                <div x-show="outside" x-cloak>
                    <x-filament::badge color="warning" icon="heroicon-o-exclamation-triangle">
                        Di luar wilayah absen — tetap bisa kirim, perlu verifikasi admin
                    </x-filament::badge>
                </div>
                <div x-show="granted && !outside" x-cloak>
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        Dalam radius wilayah absen
                    </x-filament::badge>
                </div>
            </div>

            <div class="mt-3">
                <x-filament::button type="button" size="sm" color="gray" outlined x-on:click="captureLocation()">
                    Refresh lokasi
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament::section>
