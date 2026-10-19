<?php

namespace App\Filament\Employee\Pages;

use App\Filament\Concerns\HandlesWarrantyWorkLogs;
use App\Models\Assembly;
use App\Models\WarrantyClaim;
use App\Models\WarrantyLog;
use App\Services\WarrantyClaimService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MyWarrantyClaim extends Page implements HasTable
{
    use HandlesWarrantyWorkLogs;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.employee.pages.my-warranty-claim';

    protected static ?string $navigationLabel = 'Garansi / Service';

    protected static ?string $title = 'Garansi / Service';

    public function table(Table $table): Table
    {
        return $table
            ->query(WarrantyClaim::query()->with('creator')->orderBy('entry_date', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Tiket')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('assembly_serial_number')
                    ->label('SN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Perangkat')
                    ->formatStateUsing(fn (?string $state) => WarrantyClaimService::DEVICE_TYPES[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_assembly_linked')
                    ->label('Link')
                    ->boolean(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'client_portal' ? 'Portal' : 'Internal')
                    ->color(fn (?string $state) => $state === 'client_portal' ? 'info' : 'gray'),
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
                    ->label('Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'client_portal' => 'Portal Client',
                        'internal' => 'Internal',
                    ]),
                Tables\Filters\TernaryFilter::make('is_assembly_linked')
                    ->label('Link Assembly'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\TextInput::make('invoice_number')->label('Nomor Invoice'),
                        Forms\Components\TextInput::make('client_name')->label('Nama Client'),
                        Forms\Components\TextInput::make('phone_number')->label('Telepon')->tel(),
                        Forms\Components\Select::make('device_type')
                            ->label('Perangkat')
                            ->options(WarrantyClaimService::DEVICE_TYPES),
                        Forms\Components\TextInput::make('assembly_serial_number')
                            ->label('Serial Number')
                            ->required()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('verify')
                                    ->icon('heroicon-o-check-circle')
                                    ->label('Cek SN')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        $sn = $get('assembly_serial_number');
                                        if (! $sn) {
                                            return;
                                        }
                                        $assembly = Assembly::where('serial_number', strtoupper(trim($sn)))->first();
                                        if ($assembly) {
                                            $set('device_type', $assembly->product_type);
                                            Notification::make()->success()->title('SN terhubung')->send();
                                        } else {
                                            Notification::make()->warning()->title('SN belum di ERP')->send();
                                        }
                                    })
                            ),
                        Forms\Components\Textarea::make('complaint')->label('Keluhan')->rows(3),
                        Forms\Components\Textarea::make('notes')->label('Catatan Internal')->rows(2),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();
                        $data['entry_date'] = now();
                        $data['status'] = 'pending';
                        $data['source'] = 'internal';

                        return $data;
                    })
                    ->after(function (WarrantyClaim $record) {
                        WarrantyLog::create([
                            'warranty_claim_id' => $record->id,
                            'status' => 'pending',
                            'action_type' => 'status_update',
                            'notes' => 'Garansi/service dibuat dari panel karyawan',
                            'changed_by' => Auth::id(),
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDetail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (WarrantyClaim $record) => 'Tiket '.$record->ticket_number)
                    ->modalContent(fn (WarrantyClaim $record) => view('filament.employee.components.warranty-claim-detail', [
                        'record' => $record->load('logs.changedBy'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('addLog')
                    ->label('Kerjakan')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('success')
                    ->form(fn () => $this->warrantyWorkLogForm())
                    ->action(fn (WarrantyClaim $record, array $data) => $this->processWarrantyWorkLog($record, $data)),
            ]);
    }
}
