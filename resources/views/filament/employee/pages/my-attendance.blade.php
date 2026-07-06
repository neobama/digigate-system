<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section>
            <x-slot name="heading">
                Absen Hari Ini
            </x-slot>
            <x-slot name="description">
                Buka kamera dan ambil selfie. Lokasi GPS otomatis diambil saat foto — koordinat &amp; status radius ditampilkan sebelum kirim.
            </x-slot>

            <div class="space-y-6">
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
                        class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:ring-primary-500"
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
            panel() { return document.getElementById('attendance-camera-panel'); },
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
                    1: 'Izin lokasi ditolak. Izinkan akses lokasi di pengaturan browser lalu ambil ulang foto.',
                    2: 'Lokasi tidak tersedia. Pastikan GPS perangkat aktif.',
                    3: 'Waktu habis saat mengambil lokasi. Coba ambil ulang foto di area terbuka.',
                }[err.code] || ('Gagal mengambil lokasi GPS: ' + err.message);
            },
            getLocationConfig() {
                const panel = this.panel();
                return {
                    officeLat: parseFloat(panel.dataset.officeLat),
                    officeLng: parseFloat(panel.dataset.officeLng),
                    radius: parseInt(panel.dataset.radius, 10),
                };
            },
            requestLocation() {
                return new Promise((resolve, reject) => {
                    if (!window.isSecureContext) {
                        reject(new Error('GPS membutuhkan HTTPS. Buka situs lewat https://'));
                        return;
                    }
                    if (!navigator.geolocation) {
                        reject(new Error('Browser tidak mendukung GPS. Gunakan Chrome/Safari di HP.'));
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 30000,
                        maximumAge: 0,
                    });
                });
            },
            applyLocation(position) {
                const panel = this.panel();
                const { officeLat, officeLng, radius } = this.getLocationConfig();
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const distance = this.haversine(lat, lng, officeLat, officeLng);
                const outside = distance > radius;

                $wire.set('latitude', lat, false);
                $wire.set('longitude', lng, false);

                panel.querySelector('#location-coords').textContent =
                    lat.toFixed(6) + ', ' + lng.toFixed(6);
                panel.querySelector('#location-distance').textContent =
                    new Intl.NumberFormat('id-ID').format(distance) + ' m';

                const outsideWarning = panel.querySelector('#location-outside-warning');
                const insideNote = panel.querySelector('#location-inside-note');
                outside ? this.show(outsideWarning) : this.hide(outsideWarning);
                outside ? this.hide(insideNote) : this.show(insideNote);

                this.hide(panel.querySelector('#location-error'));
                this.show(panel.querySelector('#location-summary'));
            },
            showLocationError(message) {
                const panel = this.panel();
                panel.querySelector('#location-error-text').textContent = message;
                this.hide(panel.querySelector('#location-summary'));
                this.show(panel.querySelector('#location-error'));
            },
            hideLocationUi() {
                const panel = this.panel();
                this.hide(panel.querySelector('#location-loading'));
                this.hide(panel.querySelector('#location-summary'));
                this.hide(panel.querySelector('#location-error'));
            },
            setWire(property, value) {
                $wire.set(property, value, false);
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
                const panel = this.panel();
                if (!panel) return;
                this.stopCamera();
                this.hideLocationUi();
                this.setWire('latitude', null);
                this.setWire('longitude', null);
                this.show(panel.querySelector('#camera-idle'));
                this.hide(panel.querySelector('#camera-loading'));
                this.hide(panel.querySelector('#camera-error'));
                this.hide(panel.querySelector('#camera-active'));
                this.hide(panel.querySelector('#camera-preview'));
            },
            async startCamera() {
                const panel = this.panel();
                if (!panel) return;

                const idle = panel.querySelector('#camera-idle');
                const loading = panel.querySelector('#camera-loading');
                const loadingText = panel.querySelector('#camera-loading-text');
                const errorBox = panel.querySelector('#camera-error');
                const errorText = panel.querySelector('#camera-error-text');
                const active = panel.querySelector('#camera-active');
                const video = document.getElementById('attendance-camera-video');

                this.hide(idle);
                this.hide(errorBox);
                this.hide(panel.querySelector('#camera-preview'));
                loadingText.textContent = 'Membuka kamera...';
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
            async capturePhoto() {
                const panel = this.panel();
                const video = document.getElementById('attendance-camera-video');
                const canvas = document.getElementById('attendance-camera-canvas');
                const active = panel.querySelector('#camera-active');
                const preview = panel.querySelector('#camera-preview');

                if (!video?.videoWidth || !canvas || !panel) return;

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const photoData = canvas.toDataURL('image/jpeg', 0.9);

                this.stopCamera();
                this.hide(active);
                this.hide(panel.querySelector('#camera-idle'));
                this.hide(panel.querySelector('#camera-loading'));
                this.hide(panel.querySelector('#camera-error'));

                document.getElementById('attendance-camera-preview-img').src = photoData;
                this.setWire('photo_base64', photoData);
                this.hideLocationUi();
                this.show(preview);
                this.show(panel.querySelector('#location-loading'));

                try {
                    const position = await this.requestLocation();
                    this.hide(panel.querySelector('#location-loading'));
                    this.applyLocation(position);
                } catch (err) {
                    this.hide(panel.querySelector('#location-loading'));
                    const message = err.code !== undefined
                        ? this.formatGeoError(err)
                        : (err.message || 'Gagal mengambil lokasi GPS.');
                    this.showLocationError(message);
                }
            },
            async retryLocation() {
                const panel = this.panel();

                this.hide(panel.querySelector('#location-summary'));
                this.hide(panel.querySelector('#location-error'));
                this.show(panel.querySelector('#location-loading'));

                try {
                    const position = await this.requestLocation();
                    this.hide(panel.querySelector('#location-loading'));
                    this.applyLocation(position);
                } catch (err) {
                    this.hide(panel.querySelector('#location-loading'));
                    const message = err.code !== undefined
                        ? this.formatGeoError(err)
                        : (err.message || 'Gagal mengambil lokasi GPS.');
                    this.showLocationError(message);
                }
            },
            retakePhoto() {
                this.setWire('photo_base64', null);
                this.setWire('latitude', null);
                this.setWire('longitude', null);
                this.resetCameraUi();
            },
            bind() {
                document.getElementById('btn-start-camera')?.addEventListener('click', () => this.startCamera());
                document.getElementById('btn-retry-camera')?.addEventListener('click', () => this.startCamera());
                document.getElementById('btn-capture-photo')?.addEventListener('click', () => this.capturePhoto());
                document.getElementById('btn-retake-photo')?.addEventListener('click', () => this.retakePhoto());
                document.getElementById('btn-retry-location')?.addEventListener('click', () => this.retryLocation());
            },
            resetAll() {
                this.setWire('photo_base64', null);
                this.setWire('latitude', null);
                this.setWire('longitude', null);
                this.resetCameraUi();
            },
        };

        AttendanceUi.bind();
        $wire.on('attendance-submitted', () => AttendanceUi.resetAll());
    </script>
    @endscript
</x-filament-panels::page>
