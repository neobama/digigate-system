@php
    $officeLat = config('attendance.office_latitude');
    $officeLng = config('attendance.office_longitude');
    $radius = app(\App\Services\AttendanceLocationService::class)->radiusMeters();
@endphp

<div
    x-data="{
        lat: @entangle('latitude').live,
        lng: @entangle('longitude').live,
        distance: null,
        outside: false,
        loading: false,
        granted: false,
        error: null,
        officeLat: {{ $officeLat }},
        officeLng: {{ $officeLng }},
        radius: {{ $radius }},
        init() {
            this.$watch('lat', (value) => {
                if (value != null && this.lng != null && this.distance == null) {
                    this.recalculate();
                }
            });
            Livewire.on('attendance-submitted', () => {
                this.distance = null;
                this.outside = false;
                this.granted = false;
                this.error = null;
                this.loading = false;
            });
        },
        recalculate() {
            if (this.lat == null || this.lng == null) return;
            this.distance = this.haversine(Number(this.lat), Number(this.lng), this.officeLat, this.officeLng);
            this.outside = this.distance > this.radius;
            this.granted = true;
        },
        captureLocation() {
            this.loading = true;
            this.error = null;
            this.granted = false;

            if (!window.isSecureContext) {
                this.error = 'GPS membutuhkan HTTPS. Buka situs lewat https:// bukan http://';
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
                    this.lat = position.coords.latitude;
                    this.lng = position.coords.longitude;
                    this.recalculate();
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
        <template x-if="!granted && !loading && !error">
            <button
                type="button"
                x-on:click.stop.prevent="captureLocation()"
                class="fi-btn fi-btn-color-primary fi-size-md relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-custom-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-custom-500 dark:bg-custom-500 dark:hover:bg-custom-400"
            >
                <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5" />
                Izinkan &amp; Ambil Lokasi GPS
            </button>
        </template>

        <div x-show="loading" x-cloak class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="h-4 w-4" />
            <span>Meminta izin lokasi dan mengambil koordinat...</span>
        </div>

        <div x-show="error" x-cloak class="rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-950/30 dark:ring-danger-400/30">
            <p class="text-sm font-medium text-danger-700 dark:text-danger-400" x-text="error"></p>
            <button
                type="button"
                x-on:click.stop.prevent="captureLocation()"
                class="mt-3 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20"
            >
                Coba lagi
            </button>
        </div>

        <div x-show="granted && !error" x-cloak class="rounded-lg bg-success-50 p-4 ring-1 ring-success-600/20 dark:bg-success-950/20 dark:ring-success-400/30">
            <p class="text-sm font-medium text-success-700 dark:text-success-400">Lokasi berhasil diambil</p>
            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
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
            <p x-show="outside" x-cloak class="mt-2 text-sm font-medium text-warning-600 dark:text-warning-400">
                Di luar wilayah absen — tetap bisa kirim, perlu verifikasi admin.
            </p>
            <p x-show="granted && !outside" x-cloak class="mt-2 text-sm font-medium text-success-600 dark:text-success-400">
                Dalam radius wilayah absen.
            </p>
            <button
                type="button"
                x-on:click.stop.prevent="captureLocation()"
                class="mt-3 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20"
            >
                Refresh lokasi
            </button>
        </div>
    </div>
</div>
