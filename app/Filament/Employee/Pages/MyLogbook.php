<?php

namespace App\Filament\Employee\Pages;

use App\Models\Logbook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyLogbook extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.employee.pages.my-logbook';
    protected static ?string $navigationLabel = 'Logbook Saya';
    protected static ?string $title = 'Logbook Harian';

    public function table(Table $table): Table
    {
        return $table
            ->query(Logbook::query()->where('employee_id', auth()->user()->employee?->id))
            ->columns([
                Tables\Columns\TextColumn::make('log_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity')
                    ->label('Aktivitas')
                    ->limit(50),
                Tables\Columns\TextColumn::make('photo')
                    ->label('Jumlah Foto')
                    ->getStateUsing(function (Logbook $record) {
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
                            ->disk('public')
                            ->imageEditor()
                            ->multiple()
                            ->maxFiles(10),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = auth()->user()->employee?->id;
                        return $data;
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
                            ->disk('public')
                            ->imageEditor()
                            ->multiple()
                            ->maxFiles(10),
                    ]),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

