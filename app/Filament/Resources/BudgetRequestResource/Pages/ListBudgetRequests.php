<?php

namespace App\Filament\Resources\BudgetRequestResource\Pages;

use App\Filament\Resources\BudgetRequestResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetRequests extends ListRecords
{
    protected static string $resource = BudgetRequestResource::class;

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
