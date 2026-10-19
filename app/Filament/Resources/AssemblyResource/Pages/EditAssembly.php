<?php

namespace App\Filament\Resources\AssemblyResource\Pages;

use App\Filament\Resources\AssemblyResource;
use App\Services\AssemblyInvoiceValidator;
use App\Support\AssemblySerialNumberGenerator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssembly extends EditRecord
{
    protected static string $resource = AssemblyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        AssemblyInvoiceValidator::ensureCanCreateAssembly(
            $data['invoice_id'],
            $this->record->id
        );

        $data['serial_number'] = AssemblySerialNumberGenerator::resolve(
            $data['serial_number'] ?? null,
            $data['assembly_date'] ?? $this->record->assembly_date,
            $this->record->id
        );

        return $data;
    }
}
