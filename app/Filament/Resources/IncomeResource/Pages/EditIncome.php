<?php

namespace App\Filament\Resources\IncomeResource\Pages;

use App\Filament\Resources\IncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditIncome extends EditRecord
{
    protected static string $resource = IncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->invoice_id),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_if($this->record->invoice_id, 403, 'Pemasukan dari invoice tidak bisa diedit. Ubah status invoice-nya.');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_if($record->invoice_id, 403, 'Pemasukan dari invoice tidak bisa diedit.');

        return parent::handleRecordUpdate($record, $data);
    }
}
