<?php

namespace App\Filament\Employee\Resources;

use App\Filament\Concerns\RestrictsToFinanceEmployees;
use App\Filament\Employee\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource as AdminInvoiceResource;

class InvoiceResource extends AdminInvoiceResource
{
    use RestrictsToFinanceEmployees;

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Invoice';

    protected static ?int $navigationSort = 3;

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
