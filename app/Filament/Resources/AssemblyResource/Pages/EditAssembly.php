<?php

namespace App\Filament\Resources\AssemblyResource\Pages;

use App\Filament\Resources\AssemblyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssembly extends EditRecord
{
    protected static string $resource = AssemblyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
