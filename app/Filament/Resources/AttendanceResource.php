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
                Forms\Components\Section::make('Verifikasi Koreksi Absensi')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Menunggu',
                                'approved' => 'Sah',
                                'rejected' => 'Ditolak',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan Admin')
                            ->rows(3)
                            ->helperText('Wajib diisi jika koreksi ditolak.'),
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
                Tables\Columns\TextColumn::make('proposed_recorded_at')
                    ->label('Usulan Koreksi')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->placeholder('-')
                    ->toggleable(),
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
                Tables\Columns\TextColumn::make('change_request_note')
                    ->label('Alasan Koreksi')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Attendance $record): string {
                        if ($record->hasPendingChangeRequest()) {
                            return 'change_pending';
                        }

                        return $record->status;
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'change_pending' => 'Koreksi menunggu',
                        'pending' => 'Menunggu',
                        'approved' => 'Sah',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'change_pending', 'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
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
                Tables\Filters\Filter::make('change_pending')
                    ->label('Koreksi menunggu')
                    ->query(fn (Builder $query) => $query
                        ->where(fn (Builder $q) => $q
                            ->whereNotNull('change_request_note')
                            ->orWhereNotNull('proposed_recorded_at')))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Menunggu (lama)',
                        'approved' => 'Sah',
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
                Tables\Actions\Action::make('editAttendance')
                    ->label('Ubah Absensi')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (?Attendance $record) => $record && $record->status === 'approved')
                    ->form([
                        Forms\Components\DateTimePicker::make('recorded_at')
                            ->label('Waktu Absen')
                            ->seconds(false)
                            ->required()
                            ->default(fn (Attendance $record) => $record->recorded_at),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Catatan perubahan')
                            ->rows(2)
                            ->helperText('Opsional. Tercatat sebagai catatan admin.'),
                    ])
                    ->action(function (Attendance $record, array $data): void {
                        $record->update([
                            'recorded_at' => $data['recorded_at'],
                            'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                            'proposed_recorded_at' => null,
                            'change_request_note' => null,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'status' => 'approved',
                        ]);
                    })
                    ->successNotificationTitle('Absensi diubah')
                    ->refreshAfter(),
                Tables\Actions\Action::make('approveChange')
                    ->label('Setujui Koreksi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (?Attendance $record) => $record && ($record->hasPendingChangeRequest() || $record->status === 'pending'))
                    ->requiresConfirmation()
                    ->modalHeading('Setujui koreksi absensi?')
                    ->modalDescription('Waktu absen akan diganti sesuai usulan karyawan (jika ada).')
                    ->action(function (Attendance $record) {
                        $payload = [
                            'status' => 'approved',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'admin_notes' => null,
                            'change_request_note' => null,
                            'proposed_recorded_at' => null,
                        ];

                        if ($record->proposed_recorded_at) {
                            $payload['recorded_at'] = $record->proposed_recorded_at;
                        }

                        $record->update($payload);
                    })
                    ->successNotificationTitle('Koreksi disetujui')
                    ->refreshAfter(),
                Tables\Actions\Action::make('rejectChange')
                    ->label('Tolak Koreksi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (?Attendance $record) => $record && ($record->hasPendingChangeRequest() || $record->status === 'pending'))
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Attendance $record, array $data) {
                        // Koreksi ditolak: absensi tetap sah dengan waktu asli.
                        // Pending lama (sebelum auto-valid) tetap ditolak penuh.
                        if ($record->hasPendingChangeRequest()) {
                            $record->update([
                                'status' => 'approved',
                                'verified_by' => auth()->id(),
                                'verified_at' => now(),
                                'admin_notes' => $data['admin_notes'],
                                'change_request_note' => null,
                                'proposed_recorded_at' => null,
                            ]);
                        } else {
                            $record->update([
                                'status' => 'rejected',
                                'verified_by' => auth()->id(),
                                'verified_at' => now(),
                                'admin_notes' => $data['admin_notes'],
                            ]);
                        }
                    })
                    ->successNotificationTitle('Koreksi ditolak')
                    ->refreshAfter(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveSelectedChanges')
                    ->label('Setujui Koreksi Terpilih')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui koreksi terpilih?')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $records
                            ->filter(fn (Attendance $record) => $record->hasPendingChangeRequest() || $record->status === 'pending')
                            ->each(function (Attendance $record) {
                                $payload = [
                                    'status' => 'approved',
                                    'verified_by' => auth()->id(),
                                    'verified_at' => now(),
                                    'admin_notes' => null,
                                    'change_request_note' => null,
                                    'proposed_recorded_at' => null,
                                ];

                                if ($record->proposed_recorded_at) {
                                    $payload['recorded_at'] = $record->proposed_recorded_at;
                                }

                                $record->update($payload);
                            });
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('Koreksi terpilih disetujui')
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
                        Infolists\Components\TextEntry::make('proposed_recorded_at')
                            ->label('Usulan Koreksi')
                            ->dateTime('d/m/Y H:i:s')
                            ->timezone(config('app.timezone'))
                            ->placeholder('-')
                            ->visible(fn (Attendance $record) => filled($record->proposed_recorded_at)),
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
                        Infolists\Components\TextEntry::make('change_request_note')
                            ->label('Alasan Koreksi')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (Attendance $record) => filled($record->change_request_note)),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu',
                                'approved' => 'Sah',
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
