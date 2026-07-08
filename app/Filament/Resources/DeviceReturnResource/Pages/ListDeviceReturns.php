<?php

namespace App\Filament\Resources\DeviceReturnResource\Pages;

use App\Filament\Resources\DeviceReturnResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceReturns extends ListRecords
{
    protected static string $resource = DeviceReturnResource::class;

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
