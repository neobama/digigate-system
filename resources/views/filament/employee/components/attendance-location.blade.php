@php
    $officeLat = config('attendance.office_latitude');
    $officeLng = config('attendance.office_longitude');
    $radius = app(\App\Services\AttendanceLocationService::class)->radiusMeters();
@endphp

<div
    x-data="{
        lat: @entangle('attendanceFormData.latitude'),
        lng: @entangle('attendanceFormData.longitude'),
        distance: null,
        outside: false,
        loading: true,
        error: null,
        officeLat: {{ $officeLat }},
        officeLng: {{ $officeLng }},
        radius: {{ $radius }},
        init() {
            this.captureLocation();
            Livewire.on('attendance-submitted', () => this.captureLocation());
        },
        captureLocation() {
            this.loading = true;
            this.error = null;

            if (!navigator.geolocation) {
                this.error = 'Browser tidak mendukung GPS. Gunakan perangkat dengan GPS aktif.';
                this.loading = false;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.lat = position.coords.latitude;
                    this.lng = position.coords.longitude;
                    this.distance = this.haversine(this.lat, this.lng, this.officeLat, this.officeLng);
                    this.outside = this.distance > this.radius;
                    this.loading = false;
                },
                (err) => {
                    this.error = 'Gagal mengambil lokasi GPS: ' + err.message;
                    this.loading = false;
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
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
        }
    }"
    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"
>
    <div class="space-y-2 text-sm">
        <p class="font-medium text-gray-950 dark:text-white">Status Lokasi GPS</p>

        <p x-show="loading" class="text-gray-600 dark:text-gray-400">Mengambil koordinat GPS...</p>

        <div x-show="!loading && error" class="space-y-2">
            <p class="text-danger-600 dark:text-danger-400" x-text="error"></p>
            <x-filament::button size="xs" color="gray" type="button" x-on:click="captureLocation()">
                Coba lagi
            </x-filament::button>
        </div>

        <div x-show="!loading && !error" class="space-y-1 text-gray-600 dark:text-gray-300">
            <p>
                Koordinat:
                <span class="font-mono" x-text="lat != null ? lat.toFixed(6) + ', ' + lng.toFixed(6) : '-'"></span>
            </p>
            <p>
                Jarak dari kantor:
                <span class="font-medium" x-text="distance != null ? formatDistance(distance) : '-'"></span>
                <span class="text-gray-500">(radius {{ $radius }} m)</span>
            </p>
            <p x-show="outside" class="font-medium text-warning-600 dark:text-warning-400">
                Peringatan: Anda berada di luar wilayah absen. Absensi tetap bisa dikirim, tetapi perlu verifikasi admin.
            </p>
            <p x-show="!outside" class="text-success-600 dark:text-success-400">
                Anda berada dalam radius wilayah absen.
            </p>
        </div>
    </div>
</div>
