<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssemblyResource\Pages;
use App\Filament\Resources\AssemblyResource\RelationManagers;
use App\Models\Assembly;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssemblyResource extends Resource
{
    protected static ?string $model = Assembly::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\Card::make()->schema([
                Forms\Components\Select::make('invoice_id')
                    ->relationship(
                        name: 'invoice',
                        titleAttribute: 'invoice_number',
                        modifyQueryUsing: fn (Builder $query) => $query->where('status', 'paid')
                    )
                    ->label('Pilih Invoice (Paid)')
                    ->preload()
                    ->searchable()
                    ->required(),
                
                Forms\Components\Select::make('product_type')
                    ->label('Produk Yang Dirakit')
                    ->options([
                        'Macan' => 'DigiGate Macan (i7 11700K)',
                        'Maleo' => 'DigiGate Maleo (i7 8700K)',
                    ])
                    ->live() // Memicu perubahan form secara real-time
                    ->required(),
                
                Forms\Components\DatePicker::make('assembly_date')
                    ->label('Tanggal Assembly')
                    ->default(now())
                    ->required(),
                
                Forms\Components\TextInput::make('serial_number')
                    ->label('Serial Number')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Akan di-generate otomatis saat disimpan'),

                Forms\Components\Section::make('Pilih Serial Number Komponen')
                    ->description('Hanya menampilkan SN yang tersedia di stok')
                    ->schema(function (Forms\Get $get) {
                        $type = $get('product_type');
                        if (!$type) return [];

                        // Tentukan spek berdasarkan tipe
                        $procModel = ($type === 'Macan') ? 'Processor i7 11700K' : 'Processor i7 8700K';

                        return [
                            // Dropdown Processor
                            Forms\Components\Select::make('sn_details.processor')
                                ->label("SN $procModel")
                                ->options(fn() => \App\Models\Component::where('name', $procModel)->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),
                            
                            // Dropdown RAM 1
                            Forms\Components\Select::make('sn_details.ram_1')
                                ->label('SN RAM DDR4 (Slot 1)')
                                ->options(fn() => \App\Models\Component::where('name', 'RAM DDR4')->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),

                            // Dropdown RAM 2
                            Forms\Components\Select::make('sn_details.ram_2')
                                ->label('SN RAM DDR4 (Slot 2)')
                                ->options(fn() => \App\Models\Component::where('name', 'RAM DDR4')->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),

                            // Dropdown SSD
                            Forms\Components\Select::make('sn_details.ssd')
                                ->label('SN SSD')
                                ->options(fn() => \App\Models\Component::where('name', 'SSD')->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),
                        ];
                    })->columns(2)
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assembly_date')
                    ->label('Tanggal Assembly')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->options([
                        'Macan' => 'DigiGate Macan',
                        'Maleo' => 'DigiGate Maleo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssemblies::route('/'),
            'create' => Pages\CreateAssembly::route('/create'),
            'edit' => Pages\EditAssembly::route('/{record}/edit'),
        ];
    }
}
