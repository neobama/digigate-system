<?php

namespace App\Filament\Support;

use Filament\Actions\MountableAction;

class FilamentActions
{
    public static function registerMacros(): void
    {
        MountableAction::macro('refreshAfter', function (): MountableAction {
            /** @var MountableAction $this */
            return $this->after(function ($livewire): void {
                $url = request()->header('Referer') ?: url()->current();

                if (is_object($livewire) && method_exists($livewire, 'redirect')) {
                    $livewire->redirect($url, navigate: true);
                }
            });
        });
    }
}
