<x-filament-panels::page.simple>
    @if (filament()->hasRegistration())
        <x-slot name="subheading">
            {{ __('filament-panels::pages/auth/login.actions.register.before') }}

            {{ $this->registerAction }}
        </x-slot>
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form
        id="login-form"
        wire:submit="authenticate"
        autocomplete="on"
        method="post"
        :action="url()->current()"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

    <div class="mt-6 flex justify-center">
        @if (filament()->getId() === 'admin')
            <x-filament::link :href="url('/employee/login')" color="info">
                Login sebagai Karyawan
            </x-filament::link>
        @else
            <x-filament::link :href="url('/')" color="info">
                Login sebagai Admin
            </x-filament::link>
        @endif
    </div>
</x-filament-panels::page.simple>
