<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use App\Models\WarrantyLog;
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
        $data['source'] = 'internal';

        return $data;
    }

    protected function afterCreate(): void
    {
        WarrantyLog::create([
            'warranty_claim_id' => $this->record->id,
            'status' => $this->record->status ?? 'pending',
            'action_type' => 'status_update',
            'notes' => $this->record->is_assembly_linked
                ? 'Garansi dibuat (internal) — SN terhubung ke assembly'
                : 'Garansi dibuat (internal) — SN belum terhubung ke assembly',
            'changed_by' => Auth::id(),
        ]);
    }
}
