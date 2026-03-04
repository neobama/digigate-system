<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages;

use App\Filament\Resources\WarrantyClaimResource;
use App\Models\WarrantyClaim;
use App\Models\WarrantyLog;
use App\Models\Assembly;
use App\Models\Component;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EditWarrantyClaim extends EditRecord
{
    protected static string $resource = WarrantyClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('addLog')
                ->label('Tambah Log')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
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
                    Forms\Components\Toggle::make('replace_component')
                        ->label('Ganti Komponen?')
                        ->live()
                        ->default(false),
                    Forms\Components\Select::make('component_type')
                        ->label('Jenis Komponen yang Diganti')
                        ->options([
                            'chassis' => 'Chassis',
                            'processor' => 'Processor',
                            'ram_1' => 'RAM Slot 1',
                            'ram_2' => 'RAM Slot 2',
                            'ssd' => 'SSD',
                        ])
                        ->visible(fn (Forms\Get $get) => $get('replace_component'))
                        ->required(fn (Forms\Get $get) => $get('replace_component'))
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get, WarrantyClaim $record) {
                            // Get current component SN from assembly
                            $assembly = Assembly::where('serial_number', $record->assembly_serial_number)->first();
                            if ($assembly && $assembly->sn_details) {
                                $currentSn = $assembly->sn_details[$state] ?? null;
                                $set('old_component_sn', $currentSn);
                            }
                        }),
                    Forms\Components\TextInput::make('old_component_sn')
                        ->label('SN Komponen Lama')
                        ->disabled()
                        ->visible(fn (Forms\Get $get) => $get('replace_component') && $get('component_type')),
                    Forms\Components\Select::make('new_component_sn')
                        ->label('SN Komponen Baru')
                        ->options(function (Forms\Get $get, WarrantyClaim $record) {
                            $componentType = $get('component_type');
                            if (!$componentType) {
                                return [];
                            }
                            
                            // Map component type to component name
                            $componentNameMap = [
                                'chassis' => ['Chassis Macan', 'Chassis Maleo', 'Chassis Komodo'],
                                'processor' => ['Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K'],
                                'ram_1' => ['RAM DDR4', 'RAM DDR5'],
                                'ram_2' => ['RAM DDR4', 'RAM DDR5'],
                                'ssd' => ['SSD'],
                            ];
                            
                            $componentNames = $componentNameMap[$componentType] ?? [];
                            
                            // Get available components
                            return Component::whereIn('name', $componentNames)
                                ->where('status', 'available')
                                ->pluck('sn', 'sn')
                                ->toArray();
                        })
                        ->searchable()
                        ->visible(fn (Forms\Get $get) => $get('replace_component') && $get('component_type'))
                        ->required(fn (Forms\Get $get) => $get('replace_component')),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (WarrantyClaim $record, array $data) {
                    $replaceComponent = $data['replace_component'] ?? false;
                    $oldStatus = $record->status;
                    $newStatus = $data['status'];
                    
                    // Update warranty claim status
                    $record->update([
                        'status' => $newStatus,
                        'completed_at' => $newStatus === 'completed' ? now() : null,
                    ]);
                    
                    // Create log entry
                    $logData = [
                        'warranty_claim_id' => $record->id,
                        'status' => $newStatus,
                        'notes' => $data['notes'] ?? null,
                        'changed_by' => Auth::id(),
                    ];
                    
                    if ($replaceComponent) {
                        $logData['component_type'] = $data['component_type'];
                        $logData['old_component_sn'] = $data['old_component_sn'] ?? null;
                        $logData['new_component_sn'] = $data['new_component_sn'] ?? null;
                        
                        // Update assembly sn_details
                        $assembly = Assembly::where('serial_number', $record->assembly_serial_number)->first();
                        if ($assembly) {
                            $snDetails = $assembly->sn_details ?? [];
                            $snDetails[$data['component_type']] = $data['new_component_sn'];
                            $assembly->update(['sn_details' => $snDetails]);
                        }
                        
                        // Update old component status to warranty_claim
                        if ($data['old_component_sn']) {
                            Component::where('sn', $data['old_component_sn'])->update(['status' => 'warranty_claim']);
                        }
                        
                        // Update new component status to used
                        if ($data['new_component_sn']) {
                            Component::where('sn', $data['new_component_sn'])->update(['status' => 'used']);
                        }
                    }
                    
                    WarrantyLog::create($logData);
                    
                    Notification::make()
                        ->success()
                        ->title('Log berhasil ditambahkan')
                        ->body('Log garansi telah ditambahkan' . ($replaceComponent ? ' dan komponen telah diganti' : ''))
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If status changed to completed, set completed_at
        if (isset($data['status']) && $data['status'] === 'completed' && !$this->record->completed_at) {
            $data['completed_at'] = now();
        } elseif (isset($data['status']) && $data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Create log entry if status changed
        $oldStatus = $this->record->getOriginal('status');
        $newStatus = $this->record->status;
        
        if ($oldStatus !== $newStatus) {
            WarrantyLog::create([
                'warranty_claim_id' => $this->record->id,
                'status' => $newStatus,
                'notes' => 'Status diubah dari ' . $oldStatus . ' ke ' . $newStatus,
                'changed_by' => Auth::id(),
            ]);
        }
    }
}
