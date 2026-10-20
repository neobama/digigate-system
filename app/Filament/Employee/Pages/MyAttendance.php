<?php

namespace App\Filament\Employee\Pages;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Services\AttendanceLocationService;
use App\Services\AttendancePhotoService;
use App\Services\AttendanceWorkingHoursService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyAttendance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static string $view = 'filament.employee.pages.my-attendance';

    protected static ?string $navigationLabel = 'Absen';

    protected static ?string $title = 'Absensi';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?string $photo_base64 = null;

    public ?string $description = null;

    public function getTodayTapInProperty(): ?Attendance
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return null;
        }

        return app(AttendanceWorkingHoursService::class)
            ->findForDay($employee, now(), AttendanceType::TapIn);
    }

    public function getTodayTapOutProperty(): ?Attendance
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return null;
        }

        return app(AttendanceWorkingHoursService::class)
            ->findForDay($employee, now(), AttendanceType::TapOut);
    }

    public function getNextAttendanceTypeProperty(): ?AttendanceType
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            return null;
        }

        return app(AttendanceWorkingHoursService::class)->resolveNextType($employee);
    }

    public function submitAttendance(): void
    {
        if (! auth()->user()?->employee) {
            Notification::make()
                ->title('Profil karyawan tidak ditemukan')
                ->body('Silakan hubungi admin.')
                ->danger()
                ->send();

            return;
        }

        $this->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo_base64' => ['required', 'string', 'starts_with:data:image/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'latitude.required' => 'Lokasi GPS belum terdeteksi. Klik tombol izin lokasi lalu coba lagi.',
            'longitude.required' => 'Lokasi GPS belum terdeteksi. Klik tombol izin lokasi lalu coba lagi.',
            'photo_base64.required' => 'Foto selfie wajib diambil dari kamera.',
            'photo_base64.starts_with' => 'Foto harus diambil langsung dari kamera.',
        ]);

        $employee = auth()->user()->employee;
        $workingHoursService = app(AttendanceWorkingHoursService::class);
        $attendanceType = $workingHoursService->resolveNextType($employee);

        if ($attendanceType === null) {
            Notification::make()
                ->title('Absensi hari ini selesai')
                ->body('Anda sudah tap in dan tap out hari ini.')
                ->warning()
                ->send();

            return;
        }

        if ($attendanceType === AttendanceType::TapOut && ! $workingHoursService->canTapOutToday($employee)) {
            Notification::make()
                ->title('Tap in dulu')
                ->body('Anda harus tap in terlebih dahulu sebelum tap out.')
                ->warning()
                ->send();

            return;
        }

        $locationService = app(AttendanceLocationService::class);
        $evaluation = $locationService->evaluate(
            (float) $this->latitude,
            (float) $this->longitude
        );

        $recordedAt = now();
        $photoService = app(AttendancePhotoService::class);
        $photoPath = $photoService->storeCameraPhoto($this->photo_base64);
        $stampedPhoto = $photoService->stampPhoto(
            $photoPath,
            $recordedAt,
            (float) $this->latitude,
            (float) $this->longitude,
            $attendanceType->label()
        );

        Attendance::create([
            'employee_id' => $employee->id,
            'type' => $attendanceType,
            'photo' => $stampedPhoto,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_meters' => $evaluation['distance_meters'],
            'is_within_radius' => $evaluation['is_within_radius'],
            'recorded_at' => $recordedAt,
            'status' => 'approved',
            'verified_at' => $recordedAt,
        ]);

        $this->reset(['latitude', 'longitude', 'photo_base64', 'description']);

        $typeLabel = $attendanceType->label();
        $message = $evaluation['is_within_radius']
            ? "{$typeLabel} berhasil tercatat dan langsung sah."
            : "{$typeLabel} tercatat di luar wilayah kantor (".number_format($evaluation['distance_meters'], 0, ',', '.').' m) dan langsung sah. Ajukan koreksi jika perlu.';

        Notification::make()
            ->title('Absensi sah')
            ->body($message)
            ->success()
            ->send();

        $this->dispatch('attendance-submitted');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Attendance::query()
                    ->where('employee_id', auth()->user()->employee?->id)
                    ->latest('recorded_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceType $state): string => $state->label())
                    ->color(fn (AttendanceType $state): string => match ($state) {
                        AttendanceType::TapIn => 'success',
                        AttendanceType::TapOut => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_within_radius')
                    ->label('Lokasi')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Attendance $record) => $record->is_within_radius
                        ? 'Dalam radius kantor'
                        : 'Di luar radius ('.number_format((float) $record->distance_meters, 0, ',', '.').' m)'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
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
                Tables\Columns\TextColumn::make('admin_notes')
                    ->label('Catatan Admin')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('requestCorrection')
                    ->label('Ajukan Koreksi')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Attendance $record): bool => $record->status === 'approved' && ! $record->hasPendingChangeRequest())
                    ->form([
                        \Filament\Forms\Components\DateTimePicker::make('proposed_recorded_at')
                            ->label('Waktu yang benar')
                            ->seconds(false)
                            ->required()
                            ->default(fn (Attendance $record) => $record->recorded_at),
                        \Filament\Forms\Components\Textarea::make('change_request_note')
                            ->label('Alasan koreksi')
                            ->required()
                            ->rows(3)
                            ->placeholder('Contoh: Salah jam karena GPS lambat / lupa tap out tepat waktu'),
                    ])
                    ->action(function (Attendance $record, array $data): void {
                        $record->update([
                            'proposed_recorded_at' => $data['proposed_recorded_at'],
                            'change_request_note' => $data['change_request_note'],
                        ]);

                        Notification::make()
                            ->title('Pengajuan koreksi terkirim')
                            ->body('Absensi tetap sah. Admin akan meninjau perubahan waktu.')
                            ->success()
                            ->send();
                    })
                    ->successNotificationTitle(null),
            ])
            ->emptyStateHeading('Belum ada riwayat absensi')
            ->emptyStateDescription('Kirim absensi pertama Anda menggunakan form di atas.');
    }
}
