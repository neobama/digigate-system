<?php

namespace App\Filament\Resources\DeviceReturnResource\Pages;

use App\Filament\Resources\DeviceReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceReturn extends ViewRecord
{
    protected static string $resource = DeviceReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
