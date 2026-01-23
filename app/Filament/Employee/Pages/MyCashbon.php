<?php

namespace App\Filament\Employee\Pages;

use App\Models\Cashbon;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyCashbon extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static string $view = 'filament.employee.pages.my-cashbon';
    protected static ?string $navigationLabel = 'Cashbon';
    protected static ?string $title = 'Request Cashbon';

    public function table(Table $table): Table
    {
        return $table
            ->query(Cashbon::query()->where('employee_id', auth()->user()->employee?->id))
            ->columns([
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tanggal Request')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment_months')
                    ->label('Cicilan')
                    ->formatStateUsing(fn ($state) => $state ? "$state bulan" : 'Langsung')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'paid',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Request Cashbon Baru')
                    ->form([
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
                            ->helperText(function () {
                                $employee = auth()->user()->employee;
                                if (!$employee) return '';
                                
                                $maxAllowance = $employee->getMaxCashbonPerMonth();
                                $remaining = $employee->getRemainingCashbonAllowance();
                                $used = $employee->getCurrentMonthCashbonTotal();
                                
                                return "Jatah maksimal: Rp " . number_format($maxAllowance, 0, ',', '.') . 
                                       " | Sisa jatah: Rp " . number_format($remaining, 0, ',', '.') . 
                                       " | Terpakai: Rp " . number_format($used, 0, ',', '.');
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $employee = auth()->user()->employee;
                                        if (!$employee) {
                                            $fail('Data employee tidak ditemukan.');
                                            return;
                                        }
                                        
                                        $amount = (float) $value;
                                        $remaining = $employee->getRemainingCashbonAllowance();
                                        
                                        if ($amount > $remaining) {
                                            $maxAllowance = $employee->getMaxCashbonPerMonth();
                                            $used = $employee->getCurrentMonthCashbonTotal();
                                            $fail("Jumlah melebihi sisa jatah cashbon bulanan. Jatah maksimal: Rp " . 
                                                  number_format($maxAllowance, 0, ',', '.') . 
                                                  " | Terpakai: Rp " . number_format($used, 0, ',', '.') . 
                                                  " | Sisa: Rp " . number_format($remaining, 0, ',', '.'));
                                        }
                                    };
                                },
                            ])
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
                            ->helperText('Pilih jumlah bulan untuk mencicil cashbon. Kosongkan jika ingin langsung dipotong di bulan pertama.')
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
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $employee = auth()->user()->employee;
                        if (!$employee) {
                            throw new \Exception('Data employee tidak ditemukan.');
                        }
                        
                        $amount = (float) ($data['amount'] ?? 0);
                        
                        // Auto-reject if exceeds allowance
                        if (!$employee->canRequestCashbon($amount)) {
                            $data['employee_id'] = $employee->id;
                            $data['status'] = 'rejected';
                        } else {
                            $data['employee_id'] = $employee->id;
                            $data['status'] = 'pending';
                        }
                        
                        return $data;
                    })
                    ->beforeFormFilled(function () {
                        if (!auth()->user()->employee) {
                            throw new \Exception('User tidak memiliki data employee. Silakan hubungi admin.');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Cashbon $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\DatePicker::make('request_date')
                            ->label('Tanggal Request')
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->live()
                            ->helperText(function (Cashbon $record) {
                                $employee = auth()->user()->employee;
                                if (!$employee) return '';
                                
                                $maxAllowance = $employee->getMaxCashbonPerMonth();
                                // Exclude current cashbon amount from used calculation
                                $used = $employee->getCurrentMonthCashbonTotal() - $record->amount;
                                $remaining = $maxAllowance - $used;
                                
                                return "Jatah maksimal: Rp " . number_format($maxAllowance, 0, ',', '.') . 
                                       " | Sisa jatah: Rp " . number_format($remaining, 0, ',', '.') . 
                                       " | Terpakai (selain ini): Rp " . number_format($used, 0, ',', '.');
                            })
                            ->rules([
                                function (Cashbon $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($record) {
                                        $employee = auth()->user()->employee;
                                        if (!$employee) {
                                            $fail('Data employee tidak ditemukan.');
                                            return;
                                        }
                                        
                                        $amount = (float) $value;
                                        // Exclude current cashbon amount from used calculation
                                        $used = $employee->getCurrentMonthCashbonTotal() - $record->amount;
                                        $maxAllowance = $employee->getMaxCashbonPerMonth();
                                        $remaining = $maxAllowance - $used;
                                        
                                        if ($amount > $remaining) {
                                            $fail("Jumlah melebihi sisa jatah cashbon bulanan. Jatah maksimal: Rp " . 
                                                  number_format($maxAllowance, 0, ',', '.') . 
                                                  " | Terpakai (selain ini): Rp " . number_format($used, 0, ',', '.') . 
                                                  " | Sisa: Rp " . number_format($remaining, 0, ',', '.'));
                                        }
                                    };
                                },
                            ])
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
                            ->helperText('Pilih jumlah bulan untuk mencicil cashbon. Kosongkan jika ingin langsung dipotong di bulan pertama.')
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
                    ]),
            ]);
    }
}

