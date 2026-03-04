<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarrantyClaimResource\Pages;
use App\Models\WarrantyClaim;
use App\Models\Assembly;
use App\Models\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WarrantyClaimResource extends Resource
{
    protected static ?string $model = WarrantyClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Garansi';
    protected static ?string $navigationGroup = 'Operational';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Garansi')
                    ->schema([
                        Forms\Components\TextInput::make('assembly_serial_number')
                            ->label('Serial Number Perangkat')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Masukkan SN perangkat yang akan digaransi, lalu klik tombol Verifikasi SN')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('verify')
                                    ->icon('heroicon-o-check-circle')
                                    ->label('Verifikasi SN')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        $sn = $get('assembly_serial_number');
                                        if (!$sn) {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('SN Kosong')
                                                ->body('Silakan masukkan Serial Number terlebih dahulu.')
                                                ->send();
                                            return;
                                        }
                                        
                                        $assembly = Assembly::where('serial_number', $sn)->first();
                                        if ($assembly) {
                                            \Filament\Notifications\Notification::make()
                                                ->success()
                                                ->title('SN Terdeteksi')
                                                ->body('Serial Number ditemukan di database. Perangkat: ' . ($assembly->product_type ?? 'N/A'))
                                                ->send();
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->warning()
                                                ->title('SN Tidak Ditemukan')
                                                ->body('Serial number perangkat tidak ditemukan di database. Pastikan SN sudah benar.')
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
                            ->label('Catatan')
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
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(),
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
                Infolists\Components\Section::make('Informasi Garansi')
                    ->schema([
                        Infolists\Components\TextEntry::make('assembly_serial_number')
                            ->label('Serial Number Perangkat'),
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
                        Infolists\Components\TextEntry::make('entry_date')
                            ->label('Tanggal Masuk')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Tanggal Selesai')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn (WarrantyClaim $record) => $record->completed_at !== null),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Dibuat Oleh'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(2),
                Infolists\Components\Section::make('Historical Log')
                    ->schema([
                        Infolists\Components\ViewEntry::make('logs')
                            ->label('')
                            ->view('filament.infolists.components.warranty-logs')
                            ->viewData(function (WarrantyClaim $record) {
                                return [
                                    'logs' => $record->logs,
                                ];
                            })
                            ->columnSpanFull(),
                    ]),
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
            'index' => Pages\ListWarrantyClaims::route('/'),
            'create' => Pages\CreateWarrantyClaim::route('/create'),
            'view' => Pages\ViewWarrantyClaim::route('/{record}'),
            'edit' => Pages\EditWarrantyClaim::route('/{record}/edit'),
        ];
    }
}
