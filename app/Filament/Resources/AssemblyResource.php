<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssemblyResource\Pages;
use App\Filament\Resources\AssemblyResource\RelationManagers;
use App\Models\Assembly;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssemblyResource extends Resource
{
    protected static ?string $model = Assembly::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Operational';

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
                        'Komodo' => 'DigiGate Komodo (i7 14700K)',
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
                        $procModel = match($type) {
                            'Macan' => 'Processor i7 11700K',
                            'Maleo' => 'Processor i7 8700K',
                            'Komodo' => 'Processor i7 14700K',
                            default => 'Processor i7 8700K',
                        };
                        $chassisModel = match($type) {
                            'Macan' => 'Chassis Macan',
                            'Maleo' => 'Chassis Maleo',
                            'Komodo' => 'Chassis Komodo',
                            default => 'Chassis Maleo',
                        };
                        $ramModel = ($type === 'Komodo') ? 'RAM DDR5' : 'RAM DDR4';

                        return [
                            // Dropdown Chassis
                            Forms\Components\Select::make('sn_details.chassis')
                                ->label("SN $chassisModel")
                                ->options(fn() => \App\Models\Component::where('name', $chassisModel)->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),
                            
                            // Dropdown Processor
                            Forms\Components\Select::make('sn_details.processor')
                                ->label("SN $procModel")
                                ->options(fn() => \App\Models\Component::where('name', $procModel)->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),
                            
                            // Dropdown RAM 1
                            Forms\Components\Select::make('sn_details.ram_1')
                                ->label("SN $ramModel (Slot 1)")
                                ->options(fn() => \App\Models\Component::where('name', $ramModel)->where('status', 'available')->pluck('sn', 'sn'))
                                ->required(),

                            // Dropdown RAM 2
                            Forms\Components\Select::make('sn_details.ram_2')
                                ->label("SN $ramModel (Slot 2)")
                                ->options(fn() => \App\Models\Component::where('name', $ramModel)->where('status', 'available')->pluck('sn', 'sn'))
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Assembly')
                    ->schema([
                        Infolists\Components\TextEntry::make('serial_number')
                            ->label('Serial Number')
                            ->size('lg')
                            ->weight('bold')
                            ->copyable()
                            ->copyMessage('Serial Number disalin!'),
                        Infolists\Components\TextEntry::make('invoice.invoice_number')
                            ->label('Invoice')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('product_type')
                            ->label('Produk')
                            ->formatStateUsing(fn ($state) => match($state) {
                                'Macan' => 'DigiGate Macan (i7 11700K)',
                                'Maleo' => 'DigiGate Maleo (i7 8700K)',
                                'Komodo' => 'DigiGate Komodo (i7 14700K)',
                                default => $state,
                            })
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('assembly_date')
                            ->label('Tanggal Assembly')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
                Infolists\Components\Section::make('Serial Number Komponen')
                    ->description('Detail Serial Number komponen yang digunakan dalam assembly ini')
                    ->schema([
                        Infolists\Components\TextEntry::make('sn_details.chassis')
                            ->label(fn (Assembly $record) => 'SN ' . match($record->product_type) {
                                'Macan' => 'Chassis Macan',
                                'Maleo' => 'Chassis Maleo',
                                'Komodo' => 'Chassis Komodo',
                                default => 'Chassis',
                            })
                            ->badge()
                            ->color('info')
                            ->copyable()
                            ->copyMessage('SN Chassis disalin!'),
                        Infolists\Components\TextEntry::make('sn_details.processor')
                            ->label(fn (Assembly $record) => 'SN ' . match($record->product_type) {
                                'Macan' => 'Processor i7 11700K',
                                'Maleo' => 'Processor i7 8700K',
                                'Komodo' => 'Processor i7 14700K',
                                default => 'Processor',
                            })
                            ->badge()
                            ->color('primary')
                            ->copyable()
                            ->copyMessage('SN Processor disalin!'),
                        Infolists\Components\TextEntry::make('sn_details.ram_1')
                            ->label(fn (Assembly $record) => 'SN ' . ($record->product_type === 'Komodo' ? 'RAM DDR5' : 'RAM DDR4') . ' (Slot 1)')
                            ->badge()
                            ->color('warning')
                            ->copyable()
                            ->copyMessage('SN RAM 1 disalin!'),
                        Infolists\Components\TextEntry::make('sn_details.ram_2')
                            ->label(fn (Assembly $record) => 'SN ' . ($record->product_type === 'Komodo' ? 'RAM DDR5' : 'RAM DDR4') . ' (Slot 2)')
                            ->badge()
                            ->color('warning')
                            ->copyable()
                            ->copyMessage('SN RAM 2 disalin!'),
                        Infolists\Components\TextEntry::make('sn_details.ssd')
                            ->label('SN SSD')
                            ->badge()
                            ->color('success')
                            ->copyable()
                            ->copyMessage('SN SSD disalin!'),
                    ])->columns(2),
                Infolists\Components\Section::make('Informasi Invoice')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice.client_name')
                            ->label('Client'),
                        Infolists\Components\TextEntry::make('invoice.invoice_date')
                            ->label('Tanggal Invoice')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('invoice.total_amount')
                            ->label('Total Invoice')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('invoice.status')
                            ->label('Status Invoice')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'proforma' => 'Proforma',
                                'paid' => 'Paid',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                                default => $state,
                            })
                            ->color(fn ($state) => match($state) {
                                'paid' => 'success',
                                'proforma' => 'warning',
                                'delivered' => 'info',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                    ])->columns(2)
                    ->visible(fn (Assembly $record) => $record->invoice !== null),
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
                        'Komodo' => 'DigiGate Komodo',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'view' => Pages\ViewAssembly::route('/{record}'),
            'edit' => Pages\EditAssembly::route('/{record}/edit'),
        ];
    }
}
