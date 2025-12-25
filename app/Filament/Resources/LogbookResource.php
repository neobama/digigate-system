<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogbookResource\Pages;
use App\Filament\Resources\LogbookResource\RelationManagers;
use App\Models\Logbook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class LogbookResource extends Resource
{
    protected static ?string $model = Logbook::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'HR';

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Card::make()->schema([
                Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'name')
                    ->label('Nama Karyawan')
                    ->searchable()
                ->required(),
                Forms\Components\DatePicker::make('log_date')
                ->default(now())
                ->required(),
                Forms\Components\Textarea::make('activity')
                    ->label('Aktivitas Harian')
                    ->placeholder('Apa yang dikerjakan hari ini?')
                ->required()
                ->columnSpanFull(),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto Bukti Kerja')
                    ->image()
                    ->directory('logbooks-photos')
                    ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                    ->visibility('public')
                    ->imageEditor()
                    ->multiple()
                    ->maxFiles(10)
                    ->acceptedFileTypes(['image/*'])
                    ->columnSpanFull(),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('log_date')
                    ->label('Tanggal Aktivitas')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal & Jam Submit')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity')
                    ->label('Aktivitas')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('photo')
                    ->label('Jumlah Foto')
                    ->getStateUsing(function (Logbook $record) {
                        $photos = is_array($record->photo) ? $record->photo : [];
                        return count($photos) > 0 ? count($photos) . ' foto' : '-';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('log_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('log_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('log_date', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Logbook')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Nama Karyawan'),
                        Infolists\Components\TextEntry::make('employee.nik')
                            ->label('NIK'),
                        Infolists\Components\TextEntry::make('log_date')
                            ->label('Tanggal Aktivitas')
                            ->date(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Tanggal & Jam Submit')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('activity')
                            ->label('Aktivitas')
                            ->columnSpanFull(),
                    ])->columns(2),
                Infolists\Components\Section::make('Foto Bukti Kerja')
                    ->schema([
                        Infolists\Components\ViewEntry::make('photo')
                            ->label('')
                            ->view('filament.infolists.components.logbook-photos')
                            ->viewData(function (Logbook $record) {
                                return [
                                    'photos' => is_array($record->photo) ? $record->photo : [],
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Logbook $record) => !empty($record->photo)),
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
            'index' => Pages\ListLogbooks::route('/'),
            'create' => Pages\CreateLogbook::route('/create'),
            'view' => Pages\ViewLogbook::route('/{record}'),
            'edit' => Pages\EditLogbook::route('/{record}/edit'),
        ];
}
}
