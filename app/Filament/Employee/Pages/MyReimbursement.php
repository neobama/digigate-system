<?php

namespace App\Filament\Employee\Pages;

use App\Models\Reimbursement;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

class MyReimbursement extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.employee.pages.my-reimbursement';
    protected static ?string $navigationLabel = 'Reimbursement Saya';
    protected static ?string $title = 'Request Reimbursement';

    public function table(Table $table): Table
    {
        return $table
            ->query(Reimbursement::query()->where('employee_id', auth()->user()->employee?->id))
            ->columns([
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Keperluan')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Tanggal Pengeluaran')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('proof_of_payment')
                    ->label('Bukti')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png'))
                    ->disk(env('FILESYSTEM_DISK') === 's3' ? 's3_public' : 'public')
                    ->url(fn ($record) => $record->proof_of_payment ? \Storage::disk(env('FILESYSTEM_DISK') === 's3' ? 's3_public' : 'public')->url($record->proof_of_payment) : null)
                    ->openUrlInNewTab(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'paid',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
                    ->label('Request Reimbursement Baru')
                    ->form([
                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Transport ke client, Makan siang meeting, dll'),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Tanggal Pengeluaran')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->placeholder('Tambahkan keterangan tambahan jika diperlukan'),
                        Forms\Components\FileUpload::make('proof_of_payment')
                            ->label('Bukti Pembayaran')
                            ->image()
                            ->directory('reimbursements')
                            ->disk(env('FILESYSTEM_DISK') === 's3' ? 's3_public' : 'public')
                            ->imageEditor()
                            ->maxSize(5120) // 5MB
                            ->required()
                            ->helperText('Upload bukti pembayaran (maks 5MB, format: JPG, PNG)'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = auth()->user()->employee?->id;
                        $data['status'] = 'pending';
                        return $data;
                    })
                    ->beforeFormFilled(function () {
                        if (!auth()->user()->employee) {
                            throw new \Exception('User tidak memiliki data employee. Silakan hubungi admin.');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Reimbursement $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\TextInput::make('purpose')
                            ->label('Keperluan')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Tanggal Pengeluaran')
                            ->required()
                            ->maxDate(now()),
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3),
                        Forms\Components\FileUpload::make('proof_of_payment')
                            ->label('Bukti Pembayaran')
                            ->image()
                            ->directory('reimbursements')
                            ->disk(env('FILESYSTEM_DISK') === 's3' ? 's3_public' : 'public')
                            ->imageEditor()
                            ->maxSize(5120),
                    ]),
            ]);
    }
}

