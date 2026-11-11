<?php

namespace App\Filament\Resources\AccountCodeResource\Pages;

use App\Filament\Resources\AccountCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountCode extends EditRecord
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
