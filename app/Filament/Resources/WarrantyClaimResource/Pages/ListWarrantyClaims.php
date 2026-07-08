<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarrantyClaims extends ListRecords
{
    protected static string $resource = WarrantyClaimResource::class;

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
