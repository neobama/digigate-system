<?php

namespace App\Filament\Employee\Pages;

use App\Models\Logbook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class MyLogbook extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.employee.pages.my-logbook';
    protected static ?string $navigationLabel = 'Logbook';
    protected static ?string $title = 'Daily Logbook';

    public function table(Table $table): Table
    {
        return $table
            ->query(Logbook::query()->where('employee_id', auth()->user()->employee?->id)->with('employees'))
            ->columns([
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
                        if (!$record) {
                            return '-';
                        }
                        $photos = is_array($record->photo) ? $record->photo : [];
                        return count($photos) > 0 ? count($photos) . ' foto' : '-';
                    }),
            ])
            ->filters([
                //
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
                        $data['employee_id'] = auth()->user()->employee?->id;
                        // Ensure photo is an array
                        if (isset($data['photo']) && !is_array($data['photo'])) {
                            $data['photo'] = [$data['photo']];
                        }
                        return $data;
                    })
                    ->action(function (array $data) {
                        // Extract additional employees before creating logbook
                        $additionalEmployees = $data['additional_employees'] ?? [];
                        unset($data['additional_employees']);
                        
                        // Create record directly to avoid additional processing
                        $logbook = Logbook::create($data);
                        
                        // Attach additional employees if any
                        if (!empty($additionalEmployees)) {
                            $logbook->employees()->attach($additionalEmployees);
                        }
                        
                        return $logbook;
                    })
                    ->beforeFormFilled(function () {
                        if (!auth()->user()->employee) {
                            throw new \Exception('User tidak memiliki data employee. Silakan hubungi admin.');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
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
                    ->mutateFormDataUsing(function (array $data, Logbook $record): array {
                        // Ensure photo is an array
                        if (isset($data['photo']) && !is_array($data['photo'])) {
                            $data['photo'] = [$data['photo']];
                        }
                        return $data;
                    })
                    ->after(function (Logbook $record, array $data) {
                        // Sync additional employees
                        $additionalEmployees = $data['additional_employees'] ?? [];
                        $record->employees()->sync($additionalEmployees);
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

