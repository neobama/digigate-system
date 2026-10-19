<?php

namespace App\Filament\Resources\AssemblyResource\Pages;

use App\Filament\Resources\AssemblyResource;
use App\Models\Component;
use App\Services\AssemblyInvoiceValidator;
use App\Support\AssemblySerialNumberGenerator;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAssembly extends CreateRecord
{
    protected static string $resource = AssemblyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        AssemblyInvoiceValidator::ensureCanCreateAssembly($data['invoice_id']);

        $data['serial_number'] = AssemblySerialNumberGenerator::resolve(
            $data['serial_number'] ?? null,
            $data['assembly_date'] ?? now()
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->record->sn_details;
        $serialNumbers = array_values($data);

        Component::whereIn('sn', $serialNumbers)
            ->update(['status' => 'used']);
    }
}
