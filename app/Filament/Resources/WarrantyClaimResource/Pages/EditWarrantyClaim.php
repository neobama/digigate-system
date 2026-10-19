<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Concerns\HandlesWarrantyWorkLogs;
use App\Filament\Resources\WarrantyClaimResource;
use App\Models\WarrantyClaim;
use App\Services\WarrantyClaimService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarrantyClaim extends EditRecord
{
    use HandlesWarrantyWorkLogs;

    protected static string $resource = WarrantyClaimResource::class;

    protected ?string $statusBeforeSave = null;

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
                ->action(fn (WarrantyClaim $record) => WarrantyClaimService::refreshAssemblyLink($record))
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

    protected function beforeSave(): void
    {
        $this->statusBeforeSave = $this->record->getOriginal('status') ?? $this->record->status;
    }

    protected function afterSave(): void
    {
        if ($this->statusBeforeSave === null) {
            return;
        }

        WarrantyClaimService::logStatusChange(
            $this->record,
            $this->statusBeforeSave,
            (string) $this->record->status,
        );

        $this->statusBeforeSave = null;
    }
}
