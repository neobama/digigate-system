<div
    x-data="{
        stream: null,
        photoData: @entangle('attendanceFormData.photo_base64'),
        captured: false,
        loading: true,
        error: null,
        async init() {
            await this.startCamera();
            Livewire.on('attendance-submitted', () => {
                this.photoData = null;
                this.captured = false;
                this.startCamera();
            });
        },
        async startCamera() {
            this.loading = true;
            this.error = null;
            this.stopCamera();

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
            this.startCamera();
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

    <div x-show="loading" class="rounded-xl border border-gray-200 bg-gray-50 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/50">
        Membuka kamera...
    </div>

    <div x-show="!loading && error" class="space-y-3 rounded-xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-800 dark:bg-danger-950/30">
        <p class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>
        <x-filament::button size="sm" color="gray" type="button" x-on:click="startCamera()">
            Coba buka kamera lagi
        </x-filament::button>
    </div>

    <div x-show="!loading && !error && !captured" class="space-y-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-black dark:border-gray-700">
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

    <div x-show="captured && photoData" class="space-y-3">
        <div class="overflow-hidden rounded-xl border border-success-200 dark:border-success-800">
            <img :src="photoData" alt="Preview selfie" class="mx-auto w-full max-w-md object-cover" style="max-height: 400px;" />
        </div>
        <p class="text-sm text-success-600 dark:text-success-400">Foto berhasil diambil. Klik "Ulangi Foto" jika perlu mengambil ulang.</p>
        <x-filament::button type="button" color="gray" size="sm" x-on:click="retake()">
            Ulangi Foto
        </x-filament::button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>
</div>
