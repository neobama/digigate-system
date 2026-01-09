<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashbonResource\Pages;
use App\Filament\Resources\CashbonResource\RelationManagers;
use App\Models\Cashbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CashbonResource extends Resource
{
    protected static ?string $model = Cashbon::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Cashbon';
    protected static ?string $navigationGroup = 'HR';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Karyawan')
                        ->relationship('employee', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DatePicker::make('request_date')
                        ->label('Tanggal Request')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Jumlah')
                        ->numeric()
                        ->prefix('Rp')
                        ->required(),
                    Forms\Components\Textarea::make('reason')
                        ->label('Alasan')
                        ->required()
                        ->rows(3),
                        Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'paid' => 'Paid',
                        ])
                        ->default('pending')
                        ->required(),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee')) // Eager load employee to prevent N+1 queries
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.nik')
                    ->label('NIK')
                    ->searchable(),
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tanggal Request')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'paid',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Cashbon $record) => $record->status === 'pending')
                    ->action(function (Cashbon $record) {
                        $record->status = 'approved';
                        $record->save();
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Cashbon $record) => $record->status === 'pending')
                    ->action(function (Cashbon $record) {
                        $record->status = 'rejected';
                        $record->save();
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (Cashbon $record) => $record->status === 'approved')
                    ->action(function (Cashbon $record) {
                        $record->status = 'paid';
                        $record->save();
                    })
                    ->requiresConfirmation(),
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
            'index' => Pages\ListCashbons::route('/'),
            'edit' => Pages\EditCashbon::route('/{record}/edit'),
        ];
    }
}
