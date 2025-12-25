<x-filament-pages::page>
    <form wire:submit.prevent="export">
        <x-filament::section>
            {{ $this->form }}
            
            <div class="mt-6">
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                    Export ke Excel
                </x-filament::button>
            </div>
        </x-filament::section>
    </form>
</x-filament-pages::page>
