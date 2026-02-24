<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentResource\Pages;
use App\Filament\Resources\ComponentResource\RelationManagers;
use App\Models\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComponentResource extends Resource
{
    protected static ?string $model = Component::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Operational';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\Card::make()->schema([
                Forms\Components\Select::make('name')
                    ->label('Tipe Komponen')
                    ->options([
                        'Processor i7 11700K' => 'Processor i7 11700K',
                        'Processor i7 8700K' => 'Processor i7 8700K',
                        'Processor i7 14700K' => 'Processor i7 14700K',
                        'RAM DDR4' => 'RAM DDR4',
                        'RAM DDR5' => 'RAM DDR5',
                        'SSD' => 'SSD',
                        'Chassis Macan' => 'Chassis Macan',
                        'Chassis Maleo' => 'Chassis Maleo',
                        'Chassis Komodo' => 'Chassis Komodo',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sn')
                    ->label('Serial Number (SN)')
                    ->unique(ignoreRecord: true)
                    ->required(),
                Forms\Components\TextInput::make('supplier')
                    ->label('Supplier')
                    ->required(),
                Forms\Components\TextInput::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->maxLength(255)
                    ->placeholder('Nomor invoice pembelian komponen'),
                Forms\Components\DatePicker::make('purchase_date')
                    ->label('Tanggal Pembelian')
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Tersedia',
                        'used' => 'Terpakai',
                        'warranty_claim' => 'Klaim Garansi',
                    ])
                    ->default('available')
                    ->required(),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('sn')->label('Serial Number')->searchable(),
            Tables\Columns\TextColumn::make('supplier')->sortable(),
            Tables\Columns\TextColumn::make('invoice_number')
                ->label('Nomor Invoice')
                ->searchable()
                ->toggleable(),
            Tables\Columns\BadgeColumn::make('status')
                ->colors([
                    'success' => 'available',
                    'danger' => 'used',
                    'warning' => 'warranty_claim',
                ]),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'available' => 'Tersedia',
                    'used' => 'Terpakai',
                ])
        ])
        ->headerActions([
            // Tambahkan tombol export di sini (memerlukan pxlrbt/filament-excel)
            \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Komponen')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Tipe Komponen'),
                        Infolists\Components\TextEntry::make('sn')
                            ->label('Serial Number'),
                        Infolists\Components\TextEntry::make('supplier')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Nomor Invoice'),
                        Infolists\Components\TextEntry::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->date('d/m/Y'),
                        Infolists\Components\BadgeEntry::make('status')
                            ->label('Status')
                            ->colors([
                                'success' => 'available',
                                'danger' => 'used',
                                'warning' => 'warranty_claim',
                            ]),
                    ])->columns(2),
                Infolists\Components\Section::make('Digunakan di Assembly')
                    ->schema([
                        Infolists\Components\ViewEntry::make('assembly_info')
                            ->label('')
                            ->view('filament.infolists.components.component-assembly-info')
                            ->viewData(function (Component $record) {
                                $assemblies = $record->getAssembliesUsingThisComponent();
                                return [
                                    'assemblies' => $assemblies,
                                    'componentSn' => $record->sn,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Component $record) => $record->status === 'used' && $record->getAssembliesUsingThisComponent()->isNotEmpty()),
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
            'index' => Pages\ListComponents::route('/'),
            'create' => Pages\CreateComponent::route('/create'),
            'view' => Pages\ViewComponent::route('/{record}'),
            'edit' => Pages\EditComponent::route('/{record}/edit'),
        ];
    }
}
