<?php

namespace App\Filament\Employee\Resources;

use App\Filament\Concerns\RestrictsToFinanceEmployees;
use App\Filament\Employee\Resources\IncomeResource\Pages;
use App\Filament\Resources\IncomeResource as AdminIncomeResource;

class IncomeResource extends AdminIncomeResource
{
    use RestrictsToFinanceEmployees;

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomes::route('/'),
            'create' => Pages\CreateIncome::route('/create'),
            'edit' => Pages\EditIncome::route('/{record}/edit'),
        ];
    }
}
