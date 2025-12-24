<?php

namespace App\Filament\Employee\Pages;

use App\Models\Component;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class MyComponent extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static string $view = 'filament.employee.pages.my-component';
    protected static ?string $navigationLabel = 'Komponen';
    protected static ?string $title = 'Daftar Komponen';

    public function table(Table $table): Table
    {
        return $table
            ->query(Component::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tipe Komponen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('sn')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Tanggal Pembelian')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'available',
                        'danger' => 'used',
                        'warning' => 'warranty_claim',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Tersedia',
                        'used' => 'Terpakai',
                        'warranty_claim' => 'Klaim Garansi',
                    ]),
                Tables\Filters\SelectFilter::make('name')
                    ->label('Tipe Komponen')
                    ->options([
                        'Processor i7 11700K' => 'Processor i7 11700K',
                        'Processor i7 8700K' => 'Processor i7 8700K',
                        'RAM DDR4' => 'RAM DDR4',
                        'SSD' => 'SSD',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Komponen')
                    ->model(Component::class)
                    ->form([
                        Forms\Components\Select::make('name')
                            ->label('Tipe Komponen')
                            ->options([
                                'Processor i7 11700K' => 'Processor i7 11700K',
                                'Processor i7 8700K' => 'Processor i7 8700K',
                                'RAM DDR4' => 'RAM DDR4',
                                'SSD' => 'SSD',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sn')
                            ->label('Serial Number (SN)')
                            ->unique(Component::class, 'sn', ignoreRecord: true)
                            ->required()
                            ->maxLength(255)
                            ->helperText('Masukkan serial number komponen'),
                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Nama supplier atau vendor'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'available' => 'Tersedia',
                                'used' => 'Terpakai',
                                'warranty_claim' => 'Klaim Garansi',
                            ])
                            ->default('available')
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set default status jika tidak diisi
                        if (!isset($data['status'])) {
                            $data['status'] = 'available';
                        }
                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Komponen berhasil ditambahkan')
                            ->body('Komponen baru telah ditambahkan ke sistem.')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('name')
                            ->label('Tipe Komponen')
                            ->options([
                                'Processor i7 11700K' => 'Processor i7 11700K',
                                'Processor i7 8700K' => 'Processor i7 8700K',
                                'RAM DDR4' => 'RAM DDR4',
                                'SSD' => 'SSD',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sn')
                            ->label('Serial Number (SN)')
                            ->unique(Component::class, 'sn', ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'available' => 'Tersedia',
                                'used' => 'Terpakai',
                                'warranty_claim' => 'Klaim Garansi',
                            ])
                            ->required(),
                    ])
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Komponen berhasil diupdate')
                            ->body('Data komponen telah diperbarui.')
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

