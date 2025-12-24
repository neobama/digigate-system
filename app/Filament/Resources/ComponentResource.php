<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentResource\Pages;
use App\Filament\Resources\ComponentResource\RelationManagers;
use App\Models\Component;
use Filament\Forms;
use Filament\Forms\Form;
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
                        'RAM DDR4' => 'RAM DDR4',
                        'SSD' => 'SSD',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sn')
                    ->label('Serial Number (SN)')
                    ->unique(ignoreRecord: true)
                    ->required(),
                Forms\Components\TextInput::make('supplier')
                    ->required(),
                Forms\Components\DatePicker::make('purchase_date')
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
            'edit' => Pages\EditComponent::route('/{record}/edit'),
        ];
    }
}
