<?php

namespace App\Filament\Employee\Pages;

use App\Models\WarrantyClaim;
use App\Models\WarrantyLog;
use App\Models\Assembly;
use App\Models\Component;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class MyWarrantyClaim extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string $view = 'filament.employee.pages.my-warranty-claim';
    protected static ?string $navigationLabel = 'Garansi';
    protected static ?string $title = 'Garansi Perangkat';

    public function table(Table $table): Table
    {
        return $table
            ->query(WarrantyClaim::query()->with('creator')->orderBy('entry_date', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('assembly_serial_number')
                    ->label('SN Perangkat')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Tanggal Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Tanggal Selesai')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('logs_count')
                    ->label('Jumlah Log')
                    ->counts('logs')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\TextInput::make('assembly_serial_number')
                            ->label('Serial Number Perangkat')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Masukkan SN perangkat yang akan digaransi')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $assembly = Assembly::where('serial_number', $state)->first();
                                    if (!$assembly) {
                                        $set('assembly_serial_number', null);
                                        Notification::make()
                                            ->warning()
                                            ->title('Perangkat tidak ditemukan')
                                            ->body('Serial number perangkat tidak ditemukan di database.')
                                            ->send();
                                    }
                                }
                            }),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();
                        $data['entry_date'] = now();
                        return $data;
                    })
                    ->after(function (WarrantyClaim $record) {
                        WarrantyLog::create([
                            'warranty_claim_id' => $record->id,
                            'status' => $record->status,
                            'notes' => 'Garansi dibuat',
                            'changed_by' => Auth::id(),
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewLogs')
                    ->label('Lihat Log')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->modalHeading(fn (WarrantyClaim $record) => 'Log Garansi: ' . $record->assembly_serial_number)
                    ->modalContent(function (WarrantyClaim $record) {
                        return view('filament.infolists.components.warranty-logs', [
                            'logs' => $record->logs,
                        ]);
                    })
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\Action::make('addLog')
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
                            ->options(function (Forms\Get $get) {
                                $componentType = $get('component_type');
                                if (!$componentType) {
                                    return [];
                                }
                                
                                $componentNameMap = [
                                    'chassis' => ['Chassis Macan', 'Chassis Maleo', 'Chassis Komodo'],
                                    'processor' => ['Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K'],
                                    'ram_1' => ['RAM DDR4', 'RAM DDR5'],
                                    'ram_2' => ['RAM DDR4', 'RAM DDR5'],
                                    'ssd' => ['SSD'],
                                ];
                                
                                $componentNames = $componentNameMap[$componentType] ?? [];
                                
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
                        $newStatus = $data['status'];
                        
                        $record->update([
                            'status' => $newStatus,
                            'completed_at' => $newStatus === 'completed' ? now() : null,
                        ]);
                        
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
                            
                            $assembly = Assembly::where('serial_number', $record->assembly_serial_number)->first();
                            if ($assembly) {
                                $snDetails = $assembly->sn_details ?? [];
                                $snDetails[$data['component_type']] = $data['new_component_sn'];
                                $assembly->update(['sn_details' => $snDetails]);
                            }
                            
                            if ($data['old_component_sn']) {
                                Component::where('sn', $data['old_component_sn'])->update(['status' => 'warranty_claim']);
                            }
                            
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
            ]);
    }
}
