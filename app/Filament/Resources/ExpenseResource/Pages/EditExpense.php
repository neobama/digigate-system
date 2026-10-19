<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isLinkedToSource()),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_if(
            $this->record->isLinkedToSource(),
            403,
            'Pengeluaran dari sumber (cashbon/anggaran/reimbursement/gaji) tidak bisa diedit. Ubah dokumen sumbernya.'
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_if($record->isLinkedToSource(), 403, 'Pengeluaran dari sumber tidak bisa diedit.');

        return parent::handleRecordUpdate($record, $data);
    }
}
