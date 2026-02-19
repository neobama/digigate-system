<?php

namespace App\Filament\Resources\DeviceReturnResource\Pages;

use App\Filament\Resources\DeviceReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceReturn extends EditRecord
{
    protected static string $resource = DeviceReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
