<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarrantyClaimResource\Pages;
use App\Models\Assembly;
use App\Models\WarrantyClaim;
use App\Services\WarrantyClaimService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarrantyClaimResource extends Resource
{
    protected static ?string $model = WarrantyClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Garansi / Service';

    protected static ?string $modelLabel = 'Garansi / Service';

    protected static ?string $navigationGroup = 'Operational';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Client')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Tanggal Invoice')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->helperText('Kategori garansi/repair dihitung otomatis (masa garansi 1 tahun).'),
                        Forms\Components\Placeholder::make('claim_category_preview')
                            ->label('Kategori')
                            ->content(function (Forms\Get $get, ?WarrantyClaim $record): string {
                                $date = $get('invoice_date') ?: $record?->invoice_date;
                                if (! $date) {
                                    return '-';
                                }

                                $category = WarrantyClaimService::resolveClaimCategory($date);

                                return WarrantyClaimService::CLAIM_CATEGORIES[$category] ?? $category;
                            }),
                        Forms\Components\TextInput::make('client_name')
                            ->label('Nama Perusahaan / Perorangan')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\Select::make('device_type')
                            ->label('Nama Perangkat')
                            ->options(WarrantyClaimService::DEVICE_TYPES),
                        Forms\Components\Textarea::make('complaint')
                            ->label('Keluhan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Perangkat & Status')
                    ->schema([
                        Forms\Components\TextInput::make('assembly_serial_number')
                            ->label('Serial Number Perangkat')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Masukkan SN, lalu klik Verifikasi SN')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('verify')
                                    ->icon('heroicon-o-check-circle')
                                    ->label('Verifikasi SN')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        $sn = $get('assembly_serial_number');
                                        if (! $sn) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('SN Kosong')
                                                ->body('Silakan masukkan Serial Number terlebih dahulu.')
                                                ->send();

                                            return;
                                        }

                                        $assembly = Assembly::where('serial_number', strtoupper(trim($sn)))->first();
                                        if ($assembly) {
                                            $set('device_type', $assembly->product_type);
                                            \Filament\Notifications\Notification::make()
                                                ->success()
                                                ->title('SN Terdeteksi')
                                                ->body('Terhubung ke assembly: DigiGate '.($assembly->product_type ?? 'N/A'))
                                                ->send();
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('SN Tidak Ditemukan')
                                                ->body('SN belum ada di ERP. Bisa tetap disimpan untuk link manual nanti.')
                                                ->send();
                                        }
                                    })
                            ),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required()
                            ->visibleOn('edit')
                            ->dehydrated(fn ($context) => $context === 'edit'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator')->orderBy('entry_date', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Tiket')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assembly_serial_number')
                    ->label('SN')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Perangkat')
                    ->formatStateUsing(fn (?string $state) => WarrantyClaimService::DEVICE_TYPES[$state] ?? $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('claim_category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WarrantyClaimService::CLAIM_CATEGORIES[$state] ?? ($state ?: '-'))
                    ->color(fn (?string $state) => match ($state) {
                        'warranty' => 'success',
                        'repair' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Tgl Invoice')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_assembly_linked')
                    ->label('Link Assembly')
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
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label('Sumber')
                    ->options([
                        'client_portal' => 'Portal Client',
                        'internal' => 'Internal',
                    ]),
                Tables\Filters\SelectFilter::make('claim_category')
                    ->label('Kategori')
                    ->options(WarrantyClaimService::CLAIM_CATEGORIES),
                Tables\Filters\TernaryFilter::make('is_assembly_linked')
                    ->label('Link Assembly')
                    ->trueLabel('Sudah terhubung')
                    ->falseLabel('Belum terhubung'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                Infolists\Components\Section::make('Data Client')
                    ->schema([
                        Infolists\Components\TextEntry::make('ticket_number')->label('Nomor Tiket'),
                        Infolists\Components\TextEntry::make('invoice_number')->label('Invoice')->placeholder('-'),
                        Infolists\Components\TextEntry::make('invoice_date')->label('Tanggal Invoice')->date('d/m/Y')->placeholder('-'),
                        Infolists\Components\TextEntry::make('claim_category')
                            ->label('Kategori')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => WarrantyClaimService::CLAIM_CATEGORIES[$state] ?? ($state ?: '-'))
                            ->color(fn (?string $state) => match ($state) {
                                'warranty' => 'success',
                                'repair' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('client_name')->label('Client')->placeholder('-'),
                        Infolists\Components\TextEntry::make('phone_number')->label('Telepon')->placeholder('-'),
                        Infolists\Components\TextEntry::make('device_type')
                            ->label('Perangkat')
                            ->formatStateUsing(fn (?string $state) => WarrantyClaimService::DEVICE_TYPES[$state] ?? $state)
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('source')
                            ->label('Sumber')
                            ->formatStateUsing(fn (WarrantyClaim $record) => $record->sourceLabel()),
                        Infolists\Components\TextEntry::make('complaint')
                            ->label('Keluhan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])->columns(2),
                Infolists\Components\Section::make('Perangkat & Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('assembly_serial_number')->label('Serial Number'),
                        Infolists\Components\IconEntry::make('is_assembly_linked')->label('Terhubung Assembly')->boolean(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'in_progress' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('entry_date')->label('Tanggal Masuk')->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Tanggal Selesai')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn (WarrantyClaim $record) => $record->completed_at !== null),
                        Infolists\Components\TextEntry::make('creator.name')->label('Dibuat Oleh')->placeholder('Portal Client'),
                        Infolists\Components\TextEntry::make('notes')->label('Catatan Internal')->columnSpanFull()->placeholder('-'),
                    ])->columns(2),
                Infolists\Components\Section::make('Historical Log')
                    ->schema([
                        Infolists\Components\ViewEntry::make('logs')
                            ->label('')
                            ->view('filament.infolists.components.warranty-logs')
                            ->viewData(fn (WarrantyClaim $record) => [
                                'logs' => $record->logs()->with('changedBy')->orderBy('created_at')->get(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarrantyClaims::route('/'),
            'create' => Pages\CreateWarrantyClaim::route('/create'),
            'edit' => Pages\EditWarrantyClaim::route('/{record}/edit'),
        ];
    }
}
