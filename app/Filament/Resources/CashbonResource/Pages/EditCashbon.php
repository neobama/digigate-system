<?php

namespace App\Filament\Resources\CashbonResource\Pages;

use App\Filament\Resources\CashbonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashbon extends EditRecord
{
    protected static string $resource = CashbonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
