<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceTypeResource\Pages;
use App\Models\DeviceType;
use App\Services\DeviceTypeCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class DeviceTypeResource extends Resource
{
    protected static ?string $model = DeviceType::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Tipe Perangkat';

    protected static ?string $navigationGroup = 'Operational';

    protected static ?string $modelLabel = 'Tipe Perangkat';

    protected static ?string $pluralModelLabel = 'Tipe Perangkat';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText('Kode unik dipakai di assembly/garansi, contoh: Macan, Maleo, Komodo.')
                        ->dehydrateStateUsing(fn (?string $state) => $state ? trim($state) : null),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Tampil')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: DigiGate Macan (i7 11700K)'),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

                Forms\Components\Section::make('Komponen yang Diperlukan')
                    ->description('Daftar komponen wajib untuk merakit tipe perangkat ini. Nama komponen di sini jadi pilihan saat karyawan menambah stok.')
                    ->schema([
                        Forms\Components\Repeater::make('components')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('slot_key')
                                    ->label('Slot Assembly')
                                    ->options(DeviceTypeCatalog::SLOT_OPTIONS)
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('component_name')
                                    ->label('Nama Tipe Komponen')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Harus sama dengan nama stok komponen, contoh: Chassis Macan / RAM DDR5')
                                    ->datalist(fn () => array_keys(DeviceTypeCatalog::componentNameOptions())),
                                Forms\Components\TextInput::make('label')
                                    ->label('Label Form (opsional)')
                                    ->maxLength(255)
                                    ->placeholder('Kosongkan = pakai nama komponen'),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Wajib')
                                    ->default(true),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->orderColumn('sort_order')
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Komponen')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($state['component_name'] ?? null)
                                ? (($state['slot_key'] ?? '?').' → '.$state['component_name'])
                                : 'Komponen baru')
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('components_count')
                    ->counts('components')
                    ->label('Jml Komponen')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceTypes::route('/'),
            'create' => Pages\CreateDeviceType::route('/create'),
            'edit' => Pages\EditDeviceType::route('/{record}/edit'),
        ];
    }
}
