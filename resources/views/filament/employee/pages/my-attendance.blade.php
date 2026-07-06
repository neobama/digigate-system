<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section>
            <x-slot name="heading">
                Absen Hari Ini
            </x-slot>
            <x-slot name="description">
                Izinkan akses kamera dan GPS. Foto selfie wajib diambil langsung dari kamera — tidak bisa upload dari galeri.
            </x-slot>

            <form wire:submit="submitAttendance" class="space-y-6">
                {{ $this->form }}

                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-o-camera">
                        Kirim Absensi
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Riwayat Absensi
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
