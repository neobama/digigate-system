<?php

namespace App\Filament\Resources\ComponentResource\Pages;

use App\Filament\Resources\ComponentResource;
use App\Models\Assembly;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComponent extends EditRecord
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;
        $oldStatus = $record->status;
        $newStatus = $data['status'] ?? $oldStatus;

        // If changing from 'used' to 'available', remove this SN from all assemblies
        if ($oldStatus === 'used' && $newStatus === 'available') {
            $assemblies = $record->getAssembliesUsingThisComponent();
            
            foreach ($assemblies as $assembly) {
                $snDetails = $assembly->sn_details ?? [];
                $updated = false;
                
                // Remove this SN from all fields in sn_details
                foreach ($snDetails as $key => $value) {
                    if ($value === $record->sn) {
                        unset($snDetails[$key]);
                        $updated = true;
                    }
                }
                
                // Update assembly with cleaned sn_details
                if ($updated) {
                    $assembly->update(['sn_details' => $snDetails]);
                }
            }
        }

        return $data;
    }
}
