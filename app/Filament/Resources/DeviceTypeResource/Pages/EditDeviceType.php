<?php

namespace App\Filament\Resources\DeviceTypeResource\Pages;

use App\Filament\Resources\DeviceTypeResource;
use App\Services\DeviceTypeCatalog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceType extends EditRecord
{
    protected static string $resource = DeviceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(fn () => DeviceTypeCatalog::forgetCache()),
        ];
    }

    protected function afterSave(): void
    {
        DeviceTypeCatalog::forgetCache();
    }
}
