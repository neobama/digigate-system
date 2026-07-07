<?php

namespace App\Filament\Support;

use Filament\Actions\Action;

class FilamentActions
{
    public static function registerMacros(): void
    {
        Action::macro('refreshAfter', function (): Action {
            /** @var Action $this */
            return $this->after(function ($livewire): void {
                $url = request()->header('Referer') ?: url()->current();

                if (is_object($livewire) && method_exists($livewire, 'redirect')) {
                    $livewire->redirect($url, navigate: true);
                }
            });
        });
    }
}
