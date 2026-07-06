<?php

namespace App\Filament\Employee\Pages;

use App\Models\Attendance;
use App\Services\AttendanceLocationService;
use App\Services\AttendancePhotoService;
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

        $todayStart = now('Asia/Jakarta')->startOfDay();
        $todayEnd = now('Asia/Jakarta')->endOfDay();

        $alreadySubmitted = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($alreadySubmitted) {
            Notification::make()
                ->title('Sudah absen hari ini')
                ->body('Anda sudah mengirim absensi hari ini. Tunggu verifikasi admin atau coba lagi besok.')
                ->warning()
                ->send();

            return;
        }

        $locationService = app(AttendanceLocationService::class);
        $evaluation = $locationService->evaluate(
            (float) $this->latitude,
            (float) $this->longitude
        );

        $recordedAt = now('Asia/Jakarta');
        $photoService = app(AttendancePhotoService::class);
        $photoPath = $photoService->storeCameraPhoto($this->photo_base64);
        $stampedPhoto = $photoService->stampPhoto(
            $photoPath,
            $recordedAt,
            (float) $this->latitude,
            (float) $this->longitude
        );

        Attendance::create([
            'employee_id' => $employee->id,
            'photo' => $stampedPhoto,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_meters' => $evaluation['distance_meters'],
            'is_within_radius' => $evaluation['is_within_radius'],
            'recorded_at' => $recordedAt,
            'status' => 'pending',
        ]);

        $this->reset(['latitude', 'longitude', 'photo_base64', 'description']);

        $message = $evaluation['is_within_radius']
            ? 'Absensi berhasil dikirim. Menunggu verifikasi admin.'
            : 'Absensi dikirim di luar wilayah kantor ('.number_format($evaluation['distance_meters'], 0, ',', '.').' m). Menunggu verifikasi admin.';

        Notification::make()
            ->title('Absensi tercatat')
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
                    ->label('Waktu Absen')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('admin_notes')
                    ->label('Catatan Admin')
                    ->limit(30)
                    ->placeholder('-')
                    ->visible(fn (?Attendance $record) => $record?->status === 'rejected'),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->emptyStateHeading('Belum ada riwayat absensi')
            ->emptyStateDescription('Kirim absensi pertama Anda menggunakan form di atas.');
    }
}
