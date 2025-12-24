<?php

namespace App\Filament\Resources\CashbonResource\Pages;

use App\Filament\Resources\CashbonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashbons extends ListRecords
{
    protected static string $resource = CashbonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Cashbon hanya dibuat oleh karyawan, bukan admin
        ];
    }
}
