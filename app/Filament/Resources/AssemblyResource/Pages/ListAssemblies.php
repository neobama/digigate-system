<?php

namespace App\Filament\Resources\AssemblyResource\Pages;

use App\Filament\Resources\AssemblyResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssemblies extends ListRecords
{
    protected static string $resource = AssemblyResource::class;

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
