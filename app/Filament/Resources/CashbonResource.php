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
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Reset installment_months jika amount < 2 juta
                            if ($state < 2000000) {
                                $set('installment_months', null);
                            }
                        }),
                    Forms\Components\Textarea::make('reason')
                        ->label('Alasan')
                        ->required()
                        ->rows(3),
                    Forms\Components\Select::make('installment_months')
                        ->label('Cicilan (Bulan)')
                        ->helperText('Pilih jumlah bulan untuk mencicil cashbon. Hanya muncul jika jumlah >= 2 juta. Kosongkan jika ingin langsung dipotong.')
                        ->options(function (Forms\Get $get) {
                            $amount = $get('amount');
                            if ($amount >= 2000000) {
                                $options = [null => 'Langsung dipotong (tidak dicicil)'];
                                for ($i = 1; $i <= 12; $i++) {
                                    $options[$i] = "$i bulan";
                                }
                                return $options;
                            }
                            return [];
                        })
                        ->placeholder('Pilih jumlah bulan cicilan')
                        ->visible(fn (Forms\Get $get) => $get('amount') >= 2000000)
                        ->nullable(),
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
                Tables\Columns\TextColumn::make('installment_months')
                    ->label('Cicilan')
                    ->formatStateUsing(fn ($state) => $state ? "$state bulan" : 'Langsung')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                        default => $state,
                    })
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
                        $record->update(['status' => 'approved']);
                    })
                    ->successNotificationTitle('Cashbon approved'),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Cashbon $record) => $record->status === 'pending')
                    ->action(function (Cashbon $record) {
                        $record->update(['status' => 'rejected']);
                    })
                    ->successNotificationTitle('Cashbon rejected'),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (Cashbon $record) => $record->status === 'approved')
                    ->action(function (Cashbon $record) {
                        $record->update(['status' => 'paid']);
                    })
                    ->successNotificationTitle('Cashbon marked as paid'),
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
