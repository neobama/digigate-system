<div
    x-data="{
        stream: null,
        photoData: @entangle('attendanceFormData.photo_base64'),
        captured: false,
        loading: false,
        started: false,
        error: null,
        async init() {
            Livewire.on('attendance-submitted', () => {
                this.photoData = null;
                this.captured = false;
                this.started = false;
                this.stopCamera();
            });
        },
        async startCamera() {
            this.loading = true;
            this.error = null;
            this.stopCamera();
            this.started = true;

            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = 'Browser tidak mendukung kamera. Gunakan Chrome/Safari di HP dengan HTTPS.';
                this.loading = false;
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.loading = false;
            } catch (err) {
                this.error = 'Akses kamera ditolak atau tidak tersedia. Izinkan kamera di pengaturan browser lalu muat ulang halaman.';
                this.loading = false;
            }
        },
        capturePhoto() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            if (!video.videoWidth) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.photoData = canvas.toDataURL('image/jpeg', 0.9);
            this.captured = true;
            this.stopCamera();
        },
        retake() {
            this.photoData = null;
            this.captured = false;
            this.started = false;
        },
        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
        },
        destroy() {
            this.stopCamera();
        }
    }"
    x-on:livewire:navigating.window="stopCamera()"
    class="space-y-3"
>
    <div>
        <p class="text-sm font-medium text-gray-950 dark:text-white">Selfie Absen</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Wajib ambil foto langsung dari kamera. Upload dari galeri tidak tersedia.
        </p>
    </div>

    <div x-show="!started && !loading && !error">
        <x-filament::button type="button" icon="heroicon-o-camera" x-on:click="startCamera()">
            Buka Kamera untuk Selfie
        </x-filament::button>
    </div>

    <div x-show="loading" class="flex items-center justify-center gap-2 rounded-xl bg-gray-50 p-8 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
        <x-filament::loading-indicator class="h-4 w-4" />
        Membuka kamera...
    </div>

    <x-filament::section
        x-show="!loading && error"
        x-cloak
        icon="heroicon-o-exclamation-triangle"
        icon-color="danger"
        heading="Kamera tidak tersedia"
    >
        <p class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>
        <div class="mt-3">
            <x-filament::button size="sm" color="gray" type="button" x-on:click="startCamera()">
                Coba buka kamera lagi
            </x-filament::button>
        </div>
    </x-filament::section>

    <div x-show="started && !loading && !error && !captured" class="space-y-3">
        <div class="overflow-hidden rounded-xl bg-black ring-1 ring-gray-950/5 dark:ring-white/10">
            <video
                x-ref="video"
                playsinline
                muted
                class="mx-auto w-full max-w-md mirror"
                style="transform: scaleX(-1); max-height: 400px; object-fit: cover;"
            ></video>
        </div>
        <x-filament::button type="button" icon="heroicon-o-camera" x-on:click="capturePhoto()">
            Ambil Foto Selfie
        </x-filament::button>
    </div>

        <div x-show="captured && photoData" x-cloak class="space-y-3">
        <div class="overflow-hidden rounded-xl ring-1 ring-success-600/30 dark:ring-success-400/30">
            <img :src="photoData" alt="Preview selfie" class="mx-auto w-full max-w-md object-cover" style="max-height: 400px;" />
        </div>
        <div>
            <x-filament::badge color="success" icon="heroicon-o-check-circle">
                Foto berhasil diambil
            </x-filament::badge>
        </div>
        <x-filament::button type="button" color="gray" size="sm" x-on:click="retake()">
            Ulangi Foto
        </x-filament::button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>
</div>
