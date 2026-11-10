<?php

namespace App\Filament\Employee\Pages;

use App\Models\Logbook;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyLogbook extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.employee.pages.my-logbook';

    protected static ?string $navigationLabel = 'Logbook';

    protected static ?string $title = 'Daily Logbook';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return Auth::user()?->employee?->isSupervisor()
            ? 'Logbook Tim'
            : 'Daily Logbook';
    }

    public function table(Table $table): Table
    {
        $employee = Auth::user()?->employee;
        $isSupervisor = $employee?->isSupervisor() ?? false;
        $employeeId = $employee?->id;

        return $table
            ->query(
                Logbook::query()
                    ->with(['employee', 'employees'])
                    ->when(
                        ! $isSupervisor,
                        fn (Builder $query) => $query->where('employee_id', $employeeId)
                    )
            )
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemilik')
                    ->searchable()
                    ->sortable()
                    ->visible($isSupervisor),
                Tables\Columns\TextColumn::make('log_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity')
                    ->label('Aktivitas')
                    ->limit(50),
                Tables\Columns\TextColumn::make('employees.name')
                    ->label('Karyawan Lain')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(function (Logbook $record) {
                        return $record?->employees?->pluck('name')->toArray() ?? [];
                    })
                    ->visible(fn (?Logbook $record) => $record && $record->employees && $record->employees->count() > 0),
                Tables\Columns\TextColumn::make('photo')
                    ->label('Jumlah Foto')
                    ->getStateUsing(function (?Logbook $record) {
                        if (! $record) {
                            return '-';
                        }
                        $photos = is_array($record->photo) ? $record->photo : [];

                        return count($photos) > 0 ? count($photos).' foto' : '-';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($isSupervisor),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\DatePicker::make('log_date')
                            ->label('Tanggal')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('activity')
                            ->label('Aktivitas')
                            ->required()
                            ->rows(4),
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto Bukti Kerja')
                            ->image()
                            ->directory('logbooks-photos')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->multiple()
                            ->maxFiles(10)
                            ->acceptedFileTypes(['image/*']),
                        Forms\Components\Select::make('additional_employees')
                            ->label('Tambahkan Karyawan Lain (Opsional)')
                            ->multiple()
                            ->relationship('employees', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->helperText('Pilih karyawan lain yang juga bekerja pada log ini'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = Auth::user()->employee?->id;
                        if (isset($data['photo']) && ! is_array($data['photo'])) {
                            $data['photo'] = [$data['photo']];
                        }

                        return $data;
                    })
                    ->action(function (array $data) {
                        $additionalEmployees = $data['additional_employees'] ?? [];
                        unset($data['additional_employees']);

                        $logbook = Logbook::create($data);

                        if (! empty($additionalEmployees)) {
                            $logbook->employees()->attach($additionalEmployees);
                        }

                        return $logbook;
                    })
                    ->beforeFormFilled(function () {
                        if (! Auth::user()->employee) {
                            throw new \Exception('User tidak memiliki data employee. Silakan hubungi admin.');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Logbook $record) => $record->employee_id === $employeeId)
                    ->form([
                        Forms\Components\DatePicker::make('log_date')
                            ->label('Tanggal')
                            ->required(),
                        Forms\Components\Textarea::make('activity')
                            ->label('Aktivitas')
                            ->required()
                            ->rows(4),
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto Bukti Kerja')
                            ->image()
                            ->directory('logbooks-photos')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->multiple()
                            ->maxFiles(10)
                            ->acceptedFileTypes(['image/*']),
                        Forms\Components\Select::make('additional_employees')
                            ->label('Tambahkan Karyawan Lain (Opsional)')
                            ->multiple()
                            ->relationship('employees', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->helperText('Pilih karyawan lain yang juga bekerja pada log ini')
                            ->default(fn (?Logbook $record) => $record?->employees?->pluck('id')->toArray() ?? []),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['photo']) && ! is_array($data['photo'])) {
                            $data['photo'] = [$data['photo']];
                        }

                        return $data;
                    })
                    ->after(function (Logbook $record, array $data) {
                        $additionalEmployees = $data['additional_employees'] ?? [];
                        $record->employees()->sync($additionalEmployees);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Logbook $record) => $record->employee_id === $employeeId),
            ]);
    }
}
