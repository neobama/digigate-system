<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryPaymentResource\Pages;
use App\Models\Expense;
use App\Models\SalaryPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryPaymentResource extends Resource
{
    protected static ?string $model = SalaryPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?string $navigationLabel = 'Gaji';
    protected static ?string $modelLabel = 'Gaji';
    protected static ?string $pluralModelLabel = 'Gaji';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Generate Slip Gaji')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Karyawan')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('month')
                            ->label('Bulan')
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ])
                            ->default(now()->month)
                            ->required(),
                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->default(now()->year)
                            ->required(),
                        Forms\Components\TextInput::make('adjustment_addition')
                            ->label('Tambahan Gaji')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('adjustment_deduction')
                            ->label('Pengurangan Tambahan')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->required(),
                        Forms\Components\Textarea::make('adjustment_note')
                            ->label('Catatan Penyesuaian')
                            ->rows(3)
                            ->placeholder('Contoh: Bonus lembur, denda keterlambatan, dll')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('base_salary')
                            ->label('Gaji Pokok (Auto)')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('total_cashbon')
                            ->label('Potongan Cashbon (Auto)')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('bpjs_allowance')
                            ->label('Potongan BPJS (Auto)')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('net_salary')
                            ->label('Gaji Bersih (Auto)')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('fund_source')
                            ->label('Sumber Dana')
                            ->options([
                                'kas_kecil' => 'Kas Kecil',
                                'bank_perusahaan' => 'Bank Perusahaan',
                            ])
                            ->default('bank_perusahaan')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('month')
                    ->label('Periode')
                    ->formatStateUsing(fn ($state, SalaryPayment $record) => sprintf('%02d/%s', $state, $record->year))
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Gaji Bersih')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => $state === 'paid' ? 'Sudah Digaji' : 'Draft')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'paid',
                    ]),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Tanggal Dibayar')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('year', 'desc')
            ->defaultSort('month', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Sudah Digaji')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalaryPayment $record) => $record->status !== 'paid')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pembayaran Gaji')
                    ->modalDescription('Aksi ini akan menandai gaji sebagai sudah dibayar dan membuat data Pengeluaran.')
                    ->action(function (SalaryPayment $record): void {
                        if ($record->status === 'paid') {
                            return;
                        }

                        $expense = Expense::create([
                            'description' => sprintf(
                                'Pembayaran gaji %s periode %02d/%s',
                                $record->employee?->name ?? 'Karyawan',
                                $record->month,
                                $record->year
                            ),
                            'fund_source' => $record->fund_source,
                            'expense_date' => now()->toDateString(),
                            'amount' => $record->net_salary,
                            'vendor_invoice_number' => null,
                            'account_code' => 'GAJI',
                            'proof_of_payment' => null,
                        ]);

                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'expense_id' => $expense->id,
                        ]);

                        Notification::make()
                            ->title('Gaji ditandai sudah dibayar')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalaryPayment $record) => $record->status !== 'paid'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryPayments::route('/'),
            'create' => Pages\CreateSalaryPayment::route('/create'),
            'edit' => Pages\EditSalaryPayment::route('/{record}/edit'),
        ];
    }
}

