<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Services\AttendanceWorkingHoursService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AttendanceWorkingHoursReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'filament.pages.attendance-working-hours-report';

    protected static ?string $navigationLabel = 'Rekap Jam Kerja';

    protected static ?string $navigationGroup = 'HR';

    protected static ?string $title = 'Rekap Jam Kerja Harian';

    protected static ?int $navigationSort = 6;

    public ?string $workDate = null;

    public function mount(): void
    {
        $this->workDate = now()->toDateString();
        $this->form->fill([
            'workDate' => $this->workDate,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('workDate')
                    ->label('Tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->workDate = $state;
                        $this->resetTable();
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        $service = app(AttendanceWorkingHoursService::class);
        $date = Carbon::parse($this->workDate ?? now()->toDateString(), config('app.timezone'));

        return $table
            ->query(Employee::query()->where('is_active', true)->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tap_in')
                    ->label('Tap In')
                    ->state(function (Employee $record) use ($service, $date): string {
                        $summary = $service->dailySummary($record, $date);

                        return AttendanceWorkingHoursService::formatAttendanceTime($summary['tap_in']);
                    }),
                Tables\Columns\TextColumn::make('tap_out')
                    ->label('Tap Out')
                    ->state(function (Employee $record) use ($service, $date): string {
                        $summary = $service->dailySummary($record, $date);

                        return AttendanceWorkingHoursService::formatAttendanceTime($summary['tap_out']);
                    }),
                Tables\Columns\TextColumn::make('working_hours')
                    ->label('Total Jam Kerja')
                    ->state(function (Employee $record) use ($service, $date): string {
                        $summary = $service->dailySummary($record, $date);

                        return $summary['working_hours_label'];
                    })
                    ->description(function (Employee $record) use ($service, $date): ?string {
                        $summary = $service->dailySummary($record, $date);

                        if ($summary['is_complete'] && ! $summary['is_verified']) {
                            return 'Menunggu verifikasi admin';
                        }

                        if (! $summary['tap_in']) {
                            return 'Belum tap in';
                        }

                        if (! $summary['tap_out']) {
                            return 'Belum tap out';
                        }

                        return null;
                    })
                    ->color(function (Employee $record) use ($service, $date): string {
                        $summary = $service->dailySummary($record, $date);

                        if ($summary['is_verified']) {
                            return 'success';
                        }

                        if ($summary['is_complete']) {
                            return 'warning';
                        }

                        return 'gray';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id')
                    ->label('Karyawan')
                    ->options(fn () => Employee::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Tidak ada karyawan aktif')
            ->emptyStateDescription('Tambahkan karyawan aktif untuk melihat rekap jam kerja.');
    }
}
