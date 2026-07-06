<?php

namespace App\Filament\Employee\Pages;

use App\Models\Attendance;
use App\Services\AttendanceLocationService;
use App\Services\AttendancePhotoService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyAttendance extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static string $view = 'filament.employee.pages.my-attendance';

    protected static ?string $navigationLabel = 'Absen';

    protected static ?string $title = 'Absensi';

  /**
     * @var array<string, mixed>|null
     */
    public ?array $attendanceFormData = [];

    public function mount(): void
    {
        $this->form->fill([
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\ViewField::make('location_status')
                    ->view('filament.employee.components.attendance-location'),
                Forms\Components\ViewField::make('camera_capture')
                    ->view('filament.employee.components.attendance-camera'),
                Forms\Components\Hidden::make('latitude')
                    ->required(),
                Forms\Components\Hidden::make('longitude')
                    ->required(),
                Forms\Components\Hidden::make('photo_base64'),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan (opsional)')
                    ->rows(3)
                    ->placeholder('Contoh: Meeting di luar kantor, kunjungan klien, dll.')
                    ->helperText('Keterangan ini terpisah dari teks di dalam foto.'),
            ])
            ->statePath('attendanceFormData');
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
            'attendanceFormData.latitude' => ['required', 'numeric'],
            'attendanceFormData.longitude' => ['required', 'numeric'],
            'attendanceFormData.photo_base64' => ['required', 'string', 'starts_with:data:image/'],
        ], [
            'attendanceFormData.latitude.required' => 'Lokasi GPS belum terdeteksi. Izinkan akses lokasi lalu coba lagi.',
            'attendanceFormData.longitude.required' => 'Lokasi GPS belum terdeteksi. Izinkan akses lokasi lalu coba lagi.',
            'attendanceFormData.photo_base64.required' => 'Foto selfie wajib diambil dari kamera.',
            'attendanceFormData.photo_base64.starts_with' => 'Foto harus diambil langsung dari kamera.',
        ]);

        $data = $this->form->getState();
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
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        $recordedAt = now('Asia/Jakarta');
        $photoService = app(AttendancePhotoService::class);
        $photoPath = $photoService->storeCameraPhoto($data['photo_base64']);
        $stampedPhoto = $photoService->stampPhoto(
            $photoPath,
            $recordedAt,
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        Attendance::create([
            'employee_id' => $employee->id,
            'photo' => $stampedPhoto,
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'distance_meters' => $evaluation['distance_meters'],
            'is_within_radius' => $evaluation['is_within_radius'],
            'recorded_at' => $recordedAt,
            'status' => 'pending',
        ]);

        $this->form->fill([
            'latitude' => null,
            'longitude' => null,
            'photo_base64' => null,
            'description' => null,
        ]);

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
