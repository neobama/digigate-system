<?php

namespace App\Filament\Resources\ReimbursementResource\Pages;

use App\Filament\Resources\ReimbursementResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReimbursements extends ListRecords
{
    protected static string $resource = ReimbursementResource::class;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (): ?string => null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Reimbursement hanya bisa dibuat oleh employee melalui portal
        ];
    }
}
