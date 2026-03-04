<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use App\Models\WarrantyLog;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWarrantyClaim extends CreateRecord
{
    protected static string $resource = WarrantyClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['entry_date'] = now();
        $data['status'] = $data['status'] ?? 'pending';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create initial log entry
        WarrantyLog::create([
            'warranty_claim_id' => $this->record->id,
            'status' => $this->record->status ?? 'pending',
            'notes' => 'Garansi dibuat',
            'changed_by' => Auth::id(),
        ]);
    }
}
