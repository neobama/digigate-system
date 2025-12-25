<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource\RelationManagers;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?string $navigationLabel = 'Pengeluaran';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengeluaran')
                    ->schema([
                        Forms\Components\TextInput::make('vendor_invoice_number')
                            ->label('Nomor Invoice Vendor')
                            ->maxLength(255)
                            ->placeholder('Opsional'),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Pengeluaran')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('account_code')
                            ->label('Kode Akun')
                            ->maxLength(50)
                            ->placeholder('Contoh: 5.1.01'),
                        Forms\Components\Select::make('fund_source')
                            ->label('Sumber Dana')
                            ->options([
                                'kas_kecil' => 'Kas Kecil',
                                'bank_perusahaan' => 'Bank Perusahaan',
                            ])
                            ->default('kas_kecil')
                            ->required(),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Tanggal Pengeluaran')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Nominal Pengeluaran')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Forms\Components\FileUpload::make('proof_of_payment')
                            ->label('Bukti Pembayaran')
                            ->image()
                            ->directory('expenses')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/*'])
                            ->helperText('Opsional')
                            ->columnSpanFull(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_code')
                    ->label('Kode Akun')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('fund_source')
                    ->label('Sumber Dana')
                    ->formatStateUsing(fn ($state) => $state === 'kas_kecil' ? 'Kas Kecil' : 'Bank Perusahaan')
                    ->colors([
                        'warning' => 'kas_kecil',
                        'info' => 'bank_perusahaan',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor_invoice_number')
                    ->label('No. Invoice Vendor')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fund_source')
                    ->label('Sumber Dana')
                    ->options([
                        'kas_kecil' => 'Kas Kecil',
                        'bank_perusahaan' => 'Bank Perusahaan',
                    ]),
                Tables\Filters\Filter::make('expense_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('expense_date', 'desc')
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
