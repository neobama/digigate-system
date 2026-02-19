<?php

namespace App\Filament\Resources\DeviceReturnResource\Pages;

use App\Filament\Resources\DeviceReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceReturns extends ListRecords
{
    protected static string $resource = DeviceReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
