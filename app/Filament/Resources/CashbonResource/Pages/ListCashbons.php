<?php

namespace App\Filament\Resources\CashbonResource\Pages;

use App\Filament\Resources\CashbonResource;
use App\Models\Cashbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashbons extends ListRecords
{
    protected static string $resource = CashbonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make('createTermLoan')
                ->label('Tambah Pinjaman Term')
                ->icon('heroicon-o-banknotes')
                ->model(Cashbon::class)
                ->form(CashbonResource::getTermLoanFormSchema())
                ->mutateFormDataUsing(function (array $data): array {
                    $data['is_term_loan'] = true;
                    $data['status'] = 'paid';
                    $data['paid_at'] = now();

                    return $data;
                })
                ->successNotificationTitle('Pinjaman term berhasil dicatat'),
        ];
    }
}
