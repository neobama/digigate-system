<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashbonResource\Pages;
use App\Models\Cashbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    Forms\Components\Placeholder::make('record_type')
                        ->label('Tipe')
                        ->content(fn (?Cashbon $record): string => $record?->is_term_loan
                            ? 'Pinjaman term (input admin, di luar jatah cashbon)'
                            : 'Request karyawan')
                        ->visibleOn('edit'),
                    Forms\Components\Select::make('installment_months')
                        ->label('Cicilan (Bulan)')
                        ->helperText('Pilih jumlah bulan untuk mencicil. Kosongkan = potong penuh di bulan pertama.')
                        ->options(function (Forms\Get $get, ?Cashbon $record): array {
                            if ($record?->is_term_loan) {
                                return self::installmentMonthOptionsForTermLoan();
                            }

                            $amount = (float) $get('amount');
                            if ($amount >= 2000000) {
                                return self::installmentMonthOptionsForEmployeeRequest();
                            }

                            return [];
                        })
                        ->placeholder('Pilih jumlah bulan cicilan')
                        ->visible(fn (Forms\Get $get, ?Cashbon $record): bool => $record?->is_term_loan || (float) $get('amount') >= 2000000)
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
                ])->columns(2),
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
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $record->employee?->name ?? 'N/A'),
                Tables\Columns\TextColumn::make('employee.nik')
                    ->label('NIK')
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record) => $record->employee?->nik ?? 'N/A'),
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tanggal Request')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_term_loan')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Pinjaman term' : 'Request')
                    ->color(fn ($state): string => $state ? 'info' : 'gray')
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
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Tanggal Paid')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->status === 'paid' && ! empty($record->paid_at)),
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
                Tables\Filters\TernaryFilter::make('is_term_loan')
                    ->label('Pinjaman term')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya pinjaman term')
                    ->falseLabel('Hanya request karyawan'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Cashbon $record) => $record->status === 'pending' && ! $record->is_term_loan)
                    ->action(function (Cashbon $record) {
                        $record->update(['status' => 'approved']);
                    })
                    ->successNotificationTitle('Cashbon approved')
                    ->refreshAfter(),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Cashbon $record) => $record->status === 'pending' && ! $record->is_term_loan)
                    ->action(function (Cashbon $record) {
                        $record->update(['status' => 'rejected']);
                    })
                    ->successNotificationTitle('Cashbon rejected')
                    ->refreshAfter(),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (Cashbon $record) => $record->status === 'approved' && ! $record->is_term_loan)
                    ->action(function (Cashbon $record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Cashbon marked as paid')
                    ->refreshAfter(),
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

    /**
     * Form khusus admin: pinjaman term (di luar jatah cashbon bulanan karyawan).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getTermLoanFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Pinjaman Term')
                ->description('Dicatat langsung sebagai paid. Potong gaji mengikuti jumlah bulan cicilan dan muncul di slip gaji. Tidak mengurangi jatah cashbon request karyawan.')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Karyawan')
                        ->relationship('employee', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DatePicker::make('request_date')
                        ->label('Mulai potong gaji (bulan cicilan pertama)')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('Total pinjaman')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(1),
                    Forms\Components\Textarea::make('reason')
                        ->label('Keterangan pinjaman')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('installment_months')
                        ->label('Cicilan (bulan)')
                        ->helperText('Total pinjaman dibagi rata per bulan. Pilih 1 jika ingin dipotong penuh di bulan pertama.')
                        ->options(self::installmentMonthOptionsForTermLoan())
                        ->default(3)
                        ->required(),
                ])->columns(2),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function installmentMonthOptionsForTermLoan(): array
    {
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $options[$i] = $i === 1 ? '1 bulan (potong penuh)' : "{$i} bulan";
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function installmentMonthOptionsForEmployeeRequest(): array
    {
        $options = [null => 'Langsung dipotong (tidak dicicil)'];
        for ($i = 1; $i <= 12; $i++) {
            $options[$i] = "{$i} bulan";
        }

        return $options;
    }
}
