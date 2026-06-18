<?php

namespace App\Filament\Concerns;

trait RestrictsToFinanceEmployees
{
    public static function canAccess(): bool
    {
        return auth()->user()?->employee?->isFinance() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
