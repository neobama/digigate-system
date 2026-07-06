<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Filter Tanggal
            </x-slot>
            <x-slot name="description">
                Total jam kerja dihitung dari selisih tap in dan tap out yang sudah disetujui admin pada tanggal tersebut.
            </x-slot>

            {{ $this->form }}
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
