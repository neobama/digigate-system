<?php

namespace App\Filament\Resources\LogbookResource\Pages;

use App\Filament\Resources\LogbookResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogbooks extends ListRecords
{
    protected static string $resource = LogbookResource::class;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (): ?string => null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
