<?php

namespace App\Filament\Resources\BudgetRequestResource\Pages;

use App\Filament\Resources\BudgetRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBudgetRequest extends ViewRecord
{
    protected static string $resource = BudgetRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
