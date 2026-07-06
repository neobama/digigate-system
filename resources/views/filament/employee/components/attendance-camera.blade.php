<div
    id="attendance-camera-panel"
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

    <div id="camera-idle">
        <x-filament::button type="button" id="btn-start-camera" icon="heroicon-o-camera">
            Buka Kamera untuk Selfie
        </x-filament::button>
    </div>

    <div id="camera-loading" class="hidden flex items-center justify-center gap-2 rounded-lg bg-gray-50 p-8 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
        <x-filament::loading-indicator class="h-4 w-4" />
        Membuka kamera...
    </div>

    <div id="camera-error" class="hidden rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-950/30 dark:ring-danger-400/30">
        <p id="camera-error-text" class="text-sm text-danger-700 dark:text-danger-400"></p>
        <div class="mt-3">
            <x-filament::button type="button" id="btn-retry-camera" size="sm" color="gray">
                Coba buka kamera lagi
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

    <div id="camera-preview" class="hidden space-y-3">
        <div class="overflow-hidden rounded-xl ring-1 ring-success-600/30 dark:ring-success-400/30">
            <img id="attendance-camera-preview-img" alt="Preview selfie" class="mx-auto w-full max-w-md object-cover" style="max-height: 400px;" />
        </div>
        <p class="text-sm font-medium text-success-600 dark:text-success-400">Foto berhasil diambil.</p>
        <x-filament::button type="button" id="btn-retake-photo" size="sm" color="gray" outlined>
            Ulangi Foto
        </x-filament::button>
    </div>

    <canvas id="attendance-camera-canvas" class="hidden"></canvas>
</div>
