<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceType;
use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Absensi';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Verifikasi Absensi')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Menunggu',
                                'approved' => 'Diterima',
                                'rejected' => 'Ditolak',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin')
                            ->rows(3)
                            ->helperText('Wajib diisi jika absensi ditolak.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceType $state): string => $state->label())
                    ->color(fn (AttendanceType $state): string => match ($state) {
                        AttendanceType::TapIn => 'success',
                        AttendanceType::TapOut => 'info',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Waktu Absen')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_within_radius')
                    ->label('Lokasi')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Attendance $record) => $record->is_within_radius
                        ? 'Dalam radius'
                        : 'Di luar radius ('.number_format((float) $record->distance_meters, 0, ',', '.').' m)'),
                Tables\Columns\TextColumn::make('distance_meters')
                    ->label('Jarak')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 0, ',', '.').' m' : '-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Menunggu',
                        'approved' => 'Diterima',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->label('Diverifikasi')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(AttendanceType::options()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Menunggu',
                        'approved' => 'Diterima',
                        'rejected' => 'Ditolak',
                    ]),
                Tables\Filters\TernaryFilter::make('is_within_radius')
                    ->label('Dalam Radius')
                    ->trueLabel('Dalam radius')
                    ->falseLabel('Di luar radius'),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (?Attendance $record) => $record && $record->status === 'pending')
                    ->action(function (Attendance $record) {
                        $record->update([
                            'status' => 'approved',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'admin_notes' => null,
                        ]);
                    })
                    ->successNotificationTitle('Absensi diterima')
                    ->refreshAfter(),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (?Attendance $record) => $record && $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Attendance $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'admin_notes' => $data['admin_notes'],
                        ]);
                    })
                    ->successNotificationTitle('Absensi ditolak')
                    ->refreshAfter(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveSelected')
                    ->label('Terima Terpilih')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Terima absensi terpilih?')
                    ->modalDescription('Semua absensi yang masih menunggu akan diterima.')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $records
                            ->where('status', 'pending')
                            ->each(function (Attendance $record) {
                                $record->update([
                                    'status' => 'approved',
                                    'verified_by' => auth()->id(),
                                    'verified_at' => now(),
                                    'admin_notes' => null,
                                ]);
                            });
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('Absensi terpilih diterima')
                    ->refreshAfter(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Foto Absensi')
                    ->schema([
                        Infolists\Components\ViewEntry::make('photo')
                            ->label('')
                            ->view('filament.infolists.components.attendance-photo')
                            ->viewData(fn (Attendance $record) => [
                                'photo' => $record->photo,
                            ])
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Detail Absensi')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Karyawan'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Jenis')
                            ->badge()
                            ->formatStateUsing(fn (AttendanceType $state): string => $state->label())
                            ->color(fn (AttendanceType $state): string => match ($state) {
                                AttendanceType::TapIn => 'success',
                                AttendanceType::TapOut => 'info',
                            }),
                        Infolists\Components\TextEntry::make('recorded_at')
                            ->label('Waktu Absen')
                            ->dateTime('d/m/Y H:i:s')
                            ->timezone(config('app.timezone')),
                        Infolists\Components\TextEntry::make('latitude')
                            ->label('Latitude')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 6)),
                        Infolists\Components\TextEntry::make('longitude')
                            ->label('Longitude')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 6)),
                        Infolists\Components\TextEntry::make('distance_meters')
                            ->label('Jarak dari Kantor')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.').' meter'),
                        Infolists\Components\IconEntry::make('is_within_radius')
                            ->label('Dalam Radius')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Keterangan Karyawan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu',
                                'approved' => 'Diterima',
                                'rejected' => 'Ditolak',
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Catatan Admin')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (Attendance $record) => filled($record->admin_notes)),
                        Infolists\Components\TextEntry::make('verifier.name')
                            ->label('Diverifikasi Oleh')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('verified_at')
                            ->label('Waktu Verifikasi')
                            ->dateTime('d/m/Y H:i')
                            ->timezone(config('app.timezone'))
                            ->placeholder('-'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
        ];
    }
}
