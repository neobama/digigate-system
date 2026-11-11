<?php

namespace App\Filament\Resources\AccountCodeResource\Pages;

use App\Filament\Resources\AccountCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountCodes extends ListRecords
{
    protected static string $resource = AccountCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
