<?php

namespace App\Filament\Concerns;

use App\Models\WarrantyClaim;
use App\Services\WarrantyClaimService;
use Filament\Forms;
use Filament\Notifications\Notification;

trait HandlesWarrantyWorkLogs
{
    protected function warrantyWorkLogForm(): array
    {
        return [
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Pending',
                    'in_progress' => 'In Progress',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])
                ->default(fn (WarrantyClaim $record) => $record->status)
                ->required()
                ->live(),
            Forms\Components\Select::make('action_type')
                ->label('Jenis Pekerjaan')
                ->options([
                    'status_update' => 'Update Status Saja',
                    'service' => 'Service Part (tanpa ganti stok)',
                    'replace' => 'Ganti Part dari Stok',
                ])
                ->default('status_update')
                ->required()
                ->live(),
            Forms\Components\Select::make('component_type')
                ->label('Jenis Komponen')
                ->options(WarrantyClaimService::COMPONENT_TYPES)
                ->visible(fn (Forms\Get $get) => in_array($get('action_type'), ['service', 'replace'], true))
                ->required(fn (Forms\Get $get) => in_array($get('action_type'), ['service', 'replace'], true))
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set, WarrantyClaim $record) {
                    $assembly = $record->findAssembly();
                    $set('old_component_sn', $assembly?->sn_details[$state] ?? null);
                }),
            Forms\Components\TextInput::make('old_component_sn')
                ->label('SN Komponen Saat Ini')
                ->disabled()
                ->dehydrated()
                ->visible(fn (Forms\Get $get) => in_array($get('action_type'), ['service', 'replace'], true) && filled($get('component_type'))),
            Forms\Components\Select::make('new_component_sn')
                ->label('SN Komponen Baru (dari stok)')
                ->options(fn (Forms\Get $get, WarrantyClaim $record) => WarrantyClaimService::availableComponentOptions(
                    (string) $get('component_type'),
                    $record->device_type
                ))
                ->searchable()
                ->visible(fn (Forms\Get $get) => $get('action_type') === 'replace' && filled($get('component_type')))
                ->required(fn (Forms\Get $get) => $get('action_type') === 'replace'),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    protected function processWarrantyWorkLog(WarrantyClaim $record, array $data): void
    {
        try {
            WarrantyClaimService::addWorkLog($record, $data);

            $message = match ($data['action_type'] ?? 'status_update') {
                'replace' => 'Part diganti dari stok. Part lama ditandai rusak (damaged).',
                'service' => 'Service part dicatat tanpa mengubah stok.',
                default => 'Status berhasil diperbarui.',
            };

            Notification::make()
                ->success()
                ->title('Log berhasil ditambahkan')
                ->body($message)
                ->send();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Gagal menambahkan log')
                ->body(collect($e->errors())->flatten()->first() ?? 'Validasi gagal.')
                ->send();

            throw $e;
        }
    }
}
