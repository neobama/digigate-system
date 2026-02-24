<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Filament\Resources\ActivityLogResource\RelationManagers;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only form for viewing
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'view' => 'gray',
                        'login' => 'success',
                        'logout' => 'warning',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'view' => 'View',
                        'login' => 'Login',
                        'logout' => 'Logout',
                        default => ucfirst($state),
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'view' => 'View',
                        'login' => 'Login',
                        'logout' => 'Logout',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for logs
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Informasi Aktivitas')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu')
                            ->dateTime('d/m/Y H:i:s'),
                        \Filament\Infolists\Components\TextEntry::make('user_name')
                            ->label('User'),
                        \Filament\Infolists\Components\TextEntry::make('action')
                            ->label('Aksi')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'create' => 'success',
                                'update' => 'info',
                                'delete' => 'danger',
                                'view' => 'gray',
                                'login' => 'success',
                                'logout' => 'warning',
                                default => 'primary',
                            }),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('model_type')
                            ->label('Model Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                        \Filament\Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address'),
                        \Filament\Infolists\Components\TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('url')
                            ->label('URL')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('method')
                            ->label('HTTP Method'),
                    ])->columns(2),
                \Filament\Infolists\Components\Section::make('Perubahan Data')
                    ->schema([
                        \Filament\Infolists\Components\KeyValueEntry::make('old_values')
                            ->label('Nilai Lama')
                            ->visible(fn ($record) => !empty($record->old_values)),
                        \Filament\Infolists\Components\KeyValueEntry::make('new_values')
                            ->label('Nilai Baru')
                            ->visible(fn ($record) => !empty($record->new_values)),
                    ])
                    ->visible(fn ($record) => !empty($record->old_values) || !empty($record->new_values))
                    ->columns(2),
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
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
