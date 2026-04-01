<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryPaymentResource\Pages;
use App\Models\Cashbon;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\SalaryPayment;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                            ->live()
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
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->default(now()->year)
                            ->live()
                            ->required(),
                        Forms\Components\Repeater::make('adjustments')
                            ->label('Item Tambahan / Pengurangan')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Jenis')
                                    ->options([
                                        'addition' => 'Tambahan',
                                        'deduction' => 'Pengurangan',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('description')
                                    ->label('Keterangan')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Nominal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->minValue(1),
                            ])
                            ->default([])
                            ->columns(3)
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('cashbon_preview')
                            ->label('Rincian Potongan Cashbon (Periode Dipilih)')
                            ->content(function (Forms\Get $get): HtmlString {
                                $employeeId = $get('employee_id');
                                $month = (int) ($get('month') ?? now()->month);
                                $year = (int) ($get('year') ?? now()->year);

                                if (!$employeeId) {
                                    return new HtmlString('<span style="color:#6b7280;">Pilih karyawan terlebih dahulu untuk melihat rincian cashbon.</span>');
                                }

                                $employee = Employee::find($employeeId);

                                if (!$employee) {
                                    return new HtmlString('<span style="color:#dc2626;">Data karyawan tidak ditemukan.</span>');
                                }

                                $details = self::getCashbonDetailsForPeriod($employee, $month, $year);

                                if (count($details) === 0) {
                                    return new HtmlString('<span style="color:#16a34a;">Tidak ada potongan cashbon untuk periode ini.</span>');
                                }

                                $rows = '';
                                foreach ($details as $detail) {
                                    $info = '';
                                    if (($detail['type'] ?? null) === 'cicilan') {
                                        $info = sprintf(' (Cicilan %d/%d)', $detail['installment_number'], $detail['total_installments']);
                                    }

                                    $rows .= sprintf(
                                        '<tr><td style="border:1px solid #e5e7eb;padding:6px;">%s</td><td style="border:1px solid #e5e7eb;padding:6px;">%s%s</td><td style="border:1px solid #e5e7eb;padding:6px;text-align:right;">Rp %s</td></tr>',
                                        e($detail['date']),
                                        e($detail['reason']),
                                        e($info),
                                        number_format((float) $detail['amount'], 0, ',', '.')
                                    );
                                }

                                $html = '
                                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                                        <thead>
                                            <tr>
                                                <th style="border:1px solid #e5e7eb; padding:6px; text-align:left; background:#f9fafb;">Tanggal</th>
                                                <th style="border:1px solid #e5e7eb; padding:6px; text-align:left; background:#f9fafb;">Keterangan</th>
                                                <th style="border:1px solid #e5e7eb; padding:6px; text-align:right; background:#f9fafb;">Nominal</th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $rows . '</tbody>
                                    </table>
                                ';

                                return new HtmlString($html);
                            })
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
                Tables\Actions\Action::make('lihatSlip')
                    ->label('Lihat Slip')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (SalaryPayment $record) => route('employee.salary-slip', [
                        'employee' => $record->employee_id,
                        'month' => $record->month,
                        'year' => $record->year,
                    ]))
                    ->openUrlInNewTab(),
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

                        $employee = $record->employee;
                        if ($employee?->phone_number) {
                            $period = \Carbon\Carbon::create($record->year, $record->month, 1)->translatedFormat('F Y');
                            $message = "Slip gaji {$period}";

                            try {
                                $filename = sprintf('slip-gaji-%s-%s-%02d.pdf', str_replace(' ', '-', strtolower($employee->name)), $record->year, $record->month);
                                $pdfUrl = route('employee.salary-slip', [
                                    'employee' => $employee->id,
                                    'month' => $record->month,
                                    'year' => $record->year,
                                    'pdf' => 1,
                                ]);

                                app(WhatsAppService::class)->sendDocument($employee->phone_number, $pdfUrl, $filename, 'application/pdf', $message);
                            } catch (\Throwable $exception) {
                                \Log::error('Gagal mengirim notifikasi pembayaran gaji ke WhatsApp karyawan', [
                                    'salary_payment_id' => $record->id,
                                    'employee_id' => $employee->id,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Gaji ditandai sudah dibayar')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalaryPayment $record) => $record->status !== 'paid'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SalaryPayment $record) => $record->status === 'draft'),
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

    public static function calculateMonthlyCashbon(Employee $employee, int $month, int $year): float
    {
        $details = self::getCashbonDetailsForPeriod($employee, $month, $year);

        return (float) collect($details)->sum(fn (array $detail) => (float) $detail['amount']);
    }

    public static function getCashbonDetailsForPeriod(Employee $employee, int $month, int $year): array
    {
        $currentDate = \Carbon\Carbon::create($year, $month, 1);
        $details = [];

        $cashbons = Cashbon::where('employee_id', $employee->id)
            ->where('status', 'paid')
            ->get();

        foreach ($cashbons as $cashbon) {
            $requestDate = \Carbon\Carbon::parse($cashbon->request_date);
            $installmentMonths = $cashbon->installment_months;

            if ($installmentMonths === null) {
                if ($requestDate->month == $month && $requestDate->year == $year) {
                    $details[] = [
                        'cashbon' => $cashbon,
                        'amount' => (float) $cashbon->amount,
                        'reason' => $cashbon->reason,
                        'date' => $requestDate->format('d/m/Y'),
                        'type' => 'langsung',
                    ];
                }
                continue;
            }

            $startDate = \Carbon\Carbon::create($requestDate->year, $requestDate->month, 1);
            $endDate = $startDate->copy()->addMonths($installmentMonths - 1)->endOfMonth();

            if ($currentDate->year == $startDate->year && $currentDate->month == $startDate->month) {
                $details[] = [
                    'cashbon' => $cashbon,
                    'amount' => ((float) $cashbon->amount / (int) $installmentMonths),
                    'reason' => $cashbon->reason,
                    'date' => $requestDate->format('d/m/Y'),
                    'type' => 'cicilan',
                    'installment_number' => 1,
                    'total_installments' => (int) $installmentMonths,
                ];
            } elseif ($currentDate->greaterThan($startDate) && $currentDate->lessThanOrEqualTo($endDate)) {
                $monthsDiff = $currentDate->diffInMonths($startDate);
                if ($monthsDiff < $installmentMonths) {
                    $details[] = [
                        'cashbon' => $cashbon,
                        'amount' => ((float) $cashbon->amount / (int) $installmentMonths),
                        'reason' => $cashbon->reason,
                        'date' => $requestDate->format('d/m/Y'),
                        'type' => 'cicilan',
                        'installment_number' => $monthsDiff + 1,
                        'total_installments' => (int) $installmentMonths,
                    ];
                }
            }
        }

        return $details;
    }
}

