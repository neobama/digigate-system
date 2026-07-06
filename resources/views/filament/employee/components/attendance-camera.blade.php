<div
    x-data="{
        stream: null,
        photoData: @entangle('photo_base64').live,
        captured: false,
        loading: false,
        started: false,
        error: null,
        init() {
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

            if (!window.isSecureContext) {
                this.error = 'Kamera membutuhkan HTTPS. Buka situs lewat https://';
                this.loading = false;
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = 'Browser tidak mendukung kamera. Gunakan Chrome/Safari di HP.';
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
                this.error = 'Akses kamera ditolak. Izinkan kamera di pengaturan browser lalu coba lagi.';
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
        }
    }"
    x-on:livewire:navigating.window="stopCamera()"
    class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4"
>
    <div class="flex items-center gap-3">
        <x-filament::icon icon="heroicon-o-camera" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Selfie Absen</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Wajib ambil foto langsung dari kamera — tidak bisa upload galeri.
            </p>
        </div>
    </div>

    <template x-if="!started && !loading && !error">
        <button
            type="button"
            x-on:click.stop.prevent="startCamera()"
            class="fi-btn fi-btn-color-primary fi-size-md relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-custom-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-custom-500 dark:bg-custom-500 dark:hover:bg-custom-400"
        >
            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
            Buka Kamera untuk Selfie
        </button>
    </template>

    <div x-show="loading" x-cloak class="flex items-center justify-center gap-2 rounded-lg bg-gray-50 p-8 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
        <x-filament::loading-indicator class="h-4 w-4" />
        Membuka kamera...
    </div>

    <div x-show="error" x-cloak class="rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-950/30 dark:ring-danger-400/30">
        <p class="text-sm text-danger-700 dark:text-danger-400" x-text="error"></p>
        <button
            type="button"
            x-on:click.stop.prevent="startCamera()"
            class="mt-3 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20"
        >
            Coba buka kamera lagi
        </button>
    </div>

    <div x-show="started && !loading && !error && !captured" x-cloak class="space-y-3">
        <div class="overflow-hidden rounded-xl bg-black ring-1 ring-gray-950/5 dark:ring-white/10">
            <video
                x-ref="video"
                playsinline
                muted
                class="mx-auto w-full max-w-md"
                style="transform: scaleX(-1); max-height: 400px; object-fit: cover;"
            ></video>
        </div>
        <button
            type="button"
            x-on:click.stop.prevent="capturePhoto()"
            class="fi-btn fi-btn-color-primary fi-size-md relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-custom-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-custom-500 dark:bg-custom-500 dark:hover:bg-custom-400"
        >
            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
            Ambil Foto Selfie
        </button>
    </div>

    <div x-show="captured && photoData" x-cloak class="space-y-3">
        <div class="overflow-hidden rounded-xl ring-1 ring-success-600/30 dark:ring-success-400/30">
            <img :src="photoData" alt="Preview selfie" class="mx-auto w-full max-w-md object-cover" style="max-height: 400px;" />
        </div>
        <p class="text-sm font-medium text-success-600 dark:text-success-400">Foto berhasil diambil.</p>
        <button
            type="button"
            x-on:click.stop.prevent="retake()"
            class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/20"
        >
            Ulangi Foto
        </button>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>
</div>
