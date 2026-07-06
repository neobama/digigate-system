<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section>
            <x-slot name="heading">
                Absen Hari Ini
            </x-slot>
            <x-slot name="description">
                Klik tombol izin GPS dan buka kamera terlebih dahulu. Foto selfie wajib diambil langsung dari kamera.
            </x-slot>

            <div class="space-y-6">
                @include('filament.employee.components.attendance-location')
                @include('filament.employee.components.attendance-camera')

                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            Keterangan (opsional)
                        </span>
                    </label>
                    <textarea
                        wire:model="description"
                        rows="3"
                        placeholder="Contoh: Meeting di luar kantor, kunjungan klien, dll."
                        class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500"
                    ></textarea>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Keterangan ini terpisah dari teks di dalam foto.
                    </p>
                </div>

                <div class="flex justify-end">
                    <x-filament::button
                        type="button"
                        icon="heroicon-o-paper-airplane"
                        wire:click="submitAttendance"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="submitAttendance">Kirim Absensi</span>
                        <span wire:loading wire:target="submitAttendance">Mengirim...</span>
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Riwayat Absensi
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>

    @script
    <script>
        const AttendanceUi = {
            show(el) { el?.classList.remove('hidden'); },
            hide(el) { el?.classList.add('hidden'); },
            haversine(lat1, lon1, lat2, lon2) {
                const R = 6371000;
                const toRad = (deg) => deg * Math.PI / 180;
                const dLat = toRad(lat2 - lat1);
                const dLon = toRad(lon2 - lon1);
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
                return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
            },
            formatGeoError(err) {
                return {
                    1: 'Izin lokasi ditolak. Buka pengaturan browser/situs lalu izinkan akses lokasi untuk situs ini.',
                    2: 'Lokasi tidak tersedia. Pastikan GPS perangkat aktif.',
                    3: 'Waktu habis saat mengambil lokasi. Coba lagi di area terbuka.',
                }[err.code] || ('Gagal mengambil lokasi GPS: ' + err.message);
            },
            resetLocationUi() {
                const panel = document.getElementById('attendance-location-panel');
                if (!panel) return;
                this.show(panel.querySelector('#location-idle'));
                this.hide(panel.querySelector('#location-loading'));
                this.hide(panel.querySelector('#location-error'));
                this.hide(panel.querySelector('#location-success'));
            },
            captureLocation() {
                const panel = document.getElementById('attendance-location-panel');
                if (!panel) return;

                const idle = panel.querySelector('#location-idle');
                const loading = panel.querySelector('#location-loading');
                const errorBox = panel.querySelector('#location-error');
                const errorText = panel.querySelector('#location-error-text');
                const success = panel.querySelector('#location-success');

                const officeLat = parseFloat(panel.dataset.officeLat);
                const officeLng = parseFloat(panel.dataset.officeLng);
                const radius = parseInt(panel.dataset.radius, 10);

                this.hide(idle);
                this.hide(errorBox);
                this.hide(success);
                this.show(loading);

                if (!window.isSecureContext) {
                    errorText.textContent = 'GPS membutuhkan HTTPS. Buka situs lewat https:// bukan http://';
                    this.hide(loading);
                    this.show(errorBox);
                    return;
                }

                if (!navigator.geolocation) {
                    errorText.textContent = 'Browser tidak mendukung GPS. Gunakan Chrome/Safari di HP.';
                    this.hide(loading);
                    this.show(errorBox);
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const distance = this.haversine(lat, lng, officeLat, officeLng);
                        const outside = distance > radius;

                        $wire.set('latitude', lat);
                        $wire.set('longitude', lng);

                        panel.querySelector('#location-coords').textContent =
                            lat.toFixed(6) + ', ' + lng.toFixed(6);
                        panel.querySelector('#location-distance').textContent =
                            new Intl.NumberFormat('id-ID').format(distance) + ' m';

                        const outsideWarning = panel.querySelector('#location-outside-warning');
                        const insideNote = panel.querySelector('#location-inside-note');
                        outside ? this.show(outsideWarning) : this.hide(outsideWarning);
                        outside ? this.hide(insideNote) : this.show(insideNote);

                        this.hide(loading);
                        this.show(success);
                    },
                    (err) => {
                        errorText.textContent = this.formatGeoError(err);
                        this.hide(loading);
                        this.show(errorBox);
                    },
                    { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
                );
            },
            cameraStream: null,
            stopCamera() {
                if (this.cameraStream) {
                    this.cameraStream.getTracks().forEach((track) => track.stop());
                    this.cameraStream = null;
                }
                const video = document.getElementById('attendance-camera-video');
                if (video) video.srcObject = null;
            },
            resetCameraUi() {
                const panel = document.getElementById('attendance-camera-panel');
                if (!panel) return;
                this.stopCamera();
                this.show(panel.querySelector('#camera-idle'));
                this.hide(panel.querySelector('#camera-loading'));
                this.hide(panel.querySelector('#camera-error'));
                this.hide(panel.querySelector('#camera-active'));
                this.hide(panel.querySelector('#camera-preview'));
            },
            async startCamera() {
                const panel = document.getElementById('attendance-camera-panel');
                if (!panel) return;

                const idle = panel.querySelector('#camera-idle');
                const loading = panel.querySelector('#camera-loading');
                const errorBox = panel.querySelector('#camera-error');
                const errorText = panel.querySelector('#camera-error-text');
                const active = panel.querySelector('#camera-active');
                const video = document.getElementById('attendance-camera-video');

                this.hide(idle);
                this.hide(errorBox);
                this.hide(panel.querySelector('#camera-preview'));
                this.show(loading);
                this.stopCamera();

                if (!window.isSecureContext) {
                    errorText.textContent = 'Kamera membutuhkan HTTPS. Buka situs lewat https://';
                    this.hide(loading);
                    this.show(errorBox);
                    return;
                }

                if (!navigator.mediaDevices?.getUserMedia) {
                    errorText.textContent = 'Browser tidak mendukung kamera. Gunakan Chrome/Safari di HP.';
                    this.hide(loading);
                    this.show(errorBox);
                    return;
                }

                try {
                    this.cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                        audio: false,
                    });
                    video.srcObject = this.cameraStream;
                    await video.play();
                    this.hide(loading);
                    this.show(active);
                } catch (err) {
                    errorText.textContent = 'Akses kamera ditolak. Izinkan kamera di pengaturan browser lalu coba lagi.';
                    this.hide(loading);
                    this.show(errorBox);
                }
            },
            capturePhoto() {
                const video = document.getElementById('attendance-camera-video');
                const canvas = document.getElementById('attendance-camera-canvas');
                const panel = document.getElementById('attendance-camera-panel');
                if (!video?.videoWidth || !canvas || !panel) return;

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const photoData = canvas.toDataURL('image/jpeg', 0.9);

                $wire.set('photo_base64', photoData);
                document.getElementById('attendance-camera-preview-img').src = photoData;

                this.stopCamera();
                this.hide(panel.querySelector('#camera-active'));
                this.show(panel.querySelector('#camera-preview'));
            },
            retakePhoto() {
                $wire.set('photo_base64', null);
                this.resetCameraUi();
            },
            bind() {
                document.getElementById('btn-capture-location')?.addEventListener('click', () => this.captureLocation());
                document.getElementById('btn-retry-location')?.addEventListener('click', () => this.captureLocation());
                document.getElementById('btn-refresh-location')?.addEventListener('click', () => this.captureLocation());
                document.getElementById('btn-start-camera')?.addEventListener('click', () => this.startCamera());
                document.getElementById('btn-retry-camera')?.addEventListener('click', () => this.startCamera());
                document.getElementById('btn-capture-photo')?.addEventListener('click', () => this.capturePhoto());
                document.getElementById('btn-retake-photo')?.addEventListener('click', () => this.retakePhoto());
            },
            resetAll() {
                this.resetLocationUi();
                this.resetCameraUi();
            },
        };

        AttendanceUi.bind();

        $wire.on('attendance-submitted', () => AttendanceUi.resetAll());
    </script>
    @endscript
</x-filament-panels::page>
