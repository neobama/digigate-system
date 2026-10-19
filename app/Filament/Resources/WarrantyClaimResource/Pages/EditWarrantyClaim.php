<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Concerns\HandlesWarrantyWorkLogs;
use App\Filament\Resources\WarrantyClaimResource;
use App\Models\WarrantyClaim;
use App\Models\WarrantyLog;
use App\Services\WarrantyClaimService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWarrantyClaim extends EditRecord
{
    use HandlesWarrantyWorkLogs;

    protected static string $resource = WarrantyClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('addLog')
                ->label('Tambah Log / Kerjakan')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form($this->warrantyWorkLogForm())
                ->action(fn (WarrantyClaim $record, array $data) => $this->processWarrantyWorkLog($record, $data)),
            Actions\Action::make('relinkAssembly')
                ->label('Refresh Link Assembly')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->action(function (WarrantyClaim $record) {
                    $link = WarrantyClaimService::resolveAssemblyLink($record->assembly_serial_number);
                    $record->update([
                        'is_assembly_linked' => $link['is_assembly_linked'],
                        'device_type' => $record->device_type ?: $link['device_type'],
                    ]);
                })
                ->successNotificationTitle('Link assembly diperbarui'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['status']) && $data['status'] === 'completed' && ! $this->record->completed_at) {
            $data['completed_at'] = now();
        } elseif (isset($data['status']) && $data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $oldStatus = $this->record->getOriginal('status');
        $newStatus = $this->record->status;

        if ($oldStatus !== $newStatus) {
            WarrantyLog::create([
                'warranty_claim_id' => $this->record->id,
                'status' => $newStatus,
                'action_type' => 'status_update',
                'notes' => 'Status diubah dari '.$oldStatus.' ke '.$newStatus,
                'changed_by' => Auth::id(),
            ]);
        }
    }
}
