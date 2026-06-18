<?php

namespace App\Filament\Employee\Resources;

use App\Filament\Concerns\RestrictsToFinanceEmployees;
use App\Filament\Employee\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource as AdminExpenseResource;

class ExpenseResource extends AdminExpenseResource
{
    use RestrictsToFinanceEmployees;

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
