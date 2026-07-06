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
                <div wire:ignore>
                    @include('filament.employee.components.attendance-location')
                </div>

                <div wire:ignore>
                    @include('filament.employee.components.attendance-camera')
                </div>

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
</x-filament-panels::page>
