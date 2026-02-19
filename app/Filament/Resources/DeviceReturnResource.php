<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceReturnResource\Pages;
use App\Models\DeviceReturn;
use App\Models\DeviceReturnLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DeviceReturnResource extends Resource
{
    protected static ?string $model = DeviceReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Retur Perangkat';
    protected static ?string $navigationGroup = 'Operational';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Retur')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Nomor Resi')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('device_type')
                            ->label('Jenis Perangkat')
                            ->options([
                                'Kasuari 6G 2S+' => 'Kasuari 6G 2S+',
                                'Maleo 6G 4S+' => 'Maleo 6G 4S+',
                                'Macan 6G 4S+' => 'Macan 6G 4S+',
                                'Komodo 8G 4S+ 2QS28' => 'Komodo 8G 4S+ 2QS28',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('include_mikrotik_license')
                            ->label('Include License Mikrotik')
                            ->default(false),
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nama Pelanggan')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_name')
                            ->label('Perusahaan')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->required()
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('issue_details')
                            ->label('Detail Kendala')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('proof_files')
                            ->label('Bukti Video/Foto Kendala')
                            ->multiple()
                            ->directory('device-returns')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/*', 'video/*'])
                            ->maxSize(10240) // 10MB
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'received' => 'Received',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required()
                            ->visibleOn('edit'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Nomor Resi')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Jenis Perangkat')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('SN')
                    ->searchable(),
                Tables\Columns\IconColumn::make('include_mikrotik_license')
                    ->label('License')
                    ->boolean(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'received',
                        'primary' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('device_type')
                    ->label('Jenis Perangkat')
                    ->options([
                        'Kasuari 6G 2S+' => 'Kasuari 6G 2S+',
                        'Maleo 6G 4S+' => 'Maleo 6G 4S+',
                        'Macan 6G 4S+' => 'Macan 6G 4S+',
                        'Komodo 8G 4S+ 2QS28' => 'Komodo 8G 4S+ 2QS28',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('add_log')
                    ->label('Tambah Log')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'received' => 'Received',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->placeholder('Contoh: Barang retur diterima di warehouse'),
                        Forms\Components\DateTimePicker::make('logged_at')
                            ->label('Waktu')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (DeviceReturn $record, array $data) {
                        // Create log entry
                        DeviceReturnLog::create([
                            'device_return_id' => $record->id,
                            'status' => $data['status'],
                            'description' => $data['description'] ?? null,
                            'logged_by' => Auth::id(),
                            'logged_at' => $data['logged_at'],
                        ]);

                        // Update device return status
                        $record->update(['status' => $data['status']]);
                    })
                    ->successNotificationTitle('Log berhasil ditambahkan'),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DeviceReturn $record) => $record->status !== 'cancelled')
                    ->requiresConfirmation()
                    ->action(function (DeviceReturn $record) {
                        // Create cancellation log
                        DeviceReturnLog::create([
                            'device_return_id' => $record->id,
                            'status' => 'cancelled',
                            'description' => 'Retur dibatalkan',
                            'logged_by' => Auth::id(),
                            'logged_at' => now(),
                        ]);

                        // Update status
                        $record->update(['status' => 'cancelled']);
                    })
                    ->successNotificationTitle('Retur berhasil dibatalkan'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Retur')
                    ->schema([
                        Infolists\Components\TextEntry::make('tracking_number')
                            ->label('Nomor Resi')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Nomor Invoice'),
                        Infolists\Components\TextEntry::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('device_type')
                            ->label('Jenis Perangkat')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('serial_number')
                            ->label('Serial Number'),
                        Infolists\Components\IconEntry::make('include_mikrotik_license')
                            ->label('Include License Mikrotik')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('customer_name')
                            ->label('Nama Pelanggan'),
                        Infolists\Components\TextEntry::make('company_name')
                            ->label('Perusahaan'),
                        Infolists\Components\TextEntry::make('phone_number')
                            ->label('Nomor Telepon'),
                        Infolists\Components\TextEntry::make('issue_details')
                            ->label('Detail Kendala')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'received' => 'info',
                                'in_progress' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\ViewEntry::make('proof_files')
                            ->label('Bukti Video/Foto')
                            ->view('filament.infolists.components.device-return-proof')
                            ->viewData(function (DeviceReturn $record) {
                                return [
                                    'proof_files' => $record->proof_files ?? [],
                                ];
                            })
                            ->columnSpanFull()
                            ->visible(fn (DeviceReturn $record) => !empty($record->proof_files)),
                    ])->columns(2),
                Infolists\Components\Section::make('Riwayat Status')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('logs')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'received' => 'info',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('-'),
                                Infolists\Components\TextEntry::make('logged_at')
                                    ->label('Waktu')
                                    ->dateTime('d/m/Y H:i'),
                                Infolists\Components\TextEntry::make('loggedByUser.name')
                                    ->label('Ditambahkan Oleh')
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceReturns::route('/'),
            'view' => Pages\ViewDeviceReturn::route('/{record}'),
            'edit' => Pages\EditDeviceReturn::route('/{record}/edit'),
        ];
    }
}
