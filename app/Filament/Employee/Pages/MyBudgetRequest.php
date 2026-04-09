<?php

namespace App\Filament\Employee\Pages;

use App\Models\BudgetRequest;
use App\Services\BudgetRealizationService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MyBudgetRequest extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.employee.pages.my-budget-request';

    protected static ?string $navigationLabel = 'Request Anggaran';

    protected static ?string $title = 'Request Anggaran';

    public function table(Table $table): Table
    {
        return $table
            ->query(BudgetRequest::query()->where('employee_id', auth()->user()->employee?->id))
            ->columns([
                Tables\Columns\TextColumn::make('budget_name')
                    ->label('Nama Anggaran')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('recipient_account')
                    ->label('Rekening')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Tanggal Request')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice')
                    ->label('Invoice')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }

                        return 'Lihat Invoice';
                    })
                    ->url(fn ($record) => $record->invoice ? Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($record->invoice) : null)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-document')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state, BudgetRequest $record): string {
                        return match (true) {
                            $record->status === 'pending' => 'warning',
                            $record->status === 'approved' => 'success',
                            $record->status === 'rejected' => 'danger',
                            $record->status === 'paid' && $record->realization_submitted_at === null => 'warning',
                            $record->status === 'paid' => 'success',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state, BudgetRequest $record): string {
                        if ($record->status === 'paid' && $record->realization_submitted_at === null) {
                            return 'Paid · perlu realisasi';
                        }
                        if ($record->status === 'paid') {
                            return 'Paid · tercatat';
                        }

                        return match ($record->status) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default => (string) $record->status,
                        };
                    }),
                Tables\Columns\TextColumn::make('realized_amount')
                    ->label('Nominal realisasi')
                    ->money('IDR')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
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
                    ->label('Request Anggaran Baru')
                    ->form([
                        Forms\Components\Section::make('Informasi Request Anggaran')
                            ->schema([
                                Forms\Components\TextInput::make('budget_name')
                                    ->label('Nama Anggaran')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Anggaran Project ABC'),
                                Forms\Components\Textarea::make('budget_detail')
                                    ->label('Detail Anggaran')
                                    ->required()
                                    ->rows(4)
                                    ->placeholder('Jelaskan detail penggunaan anggaran...')
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('invoice')
                                    ->label('Invoice (Opsional)')
                                    ->directory('budget-requests/invoices')
                                    ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                                    ->visibility('public')
                                    ->image()
                                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                                    ->maxSize(5120) // 5MB
                                    ->extraAttributes([
                                        'accept' => 'image/*,application/pdf',
                                        'capture' => 'environment', // Aktifkan camera capture
                                    ])
                                    ->helperText('Upload invoice jika ada (maks 5MB). Bisa ambil foto langsung dari kamera atau upload file (JPG, PNG, PDF).'),
                                Forms\Components\TextInput::make('recipient_account')
                                    ->label('Rekening Penerima')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: BCA 1234567890 a.n. Nama'),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Nominal Anggaran')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\DatePicker::make('request_date')
                                    ->label('Tanggal Request')
                                    ->default(now())
                                    ->required(),
                            ])->columns(2),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = auth()->user()->employee?->id;
                        $data['status'] = 'pending';

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('realisasi')
                    ->label('Input realisasi')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->modalHeading('Realisasi anggaran')
                    ->modalDescription('Setelah dana dipakai, isi nominal aktual dan unggah bukti pembelian. Baru setelah ini pengeluaran masuk ke laporan keuangan.')
                    ->visible(fn (BudgetRequest $record): bool => $record->needsRealization())
                    ->form([
                        Forms\Components\TextInput::make('realized_amount')
                            ->label('Nominal realisasi (aktual)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0.01)
                            ->helperText('Total pengeluaran sesuai bukti.'),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Tanggal pengeluaran')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('realization_notes')
                            ->label('Keterangan / rincian')
                            ->rows(3)
                            ->placeholder('Contoh: pembelian lisensi Cursor, toko X, dll.')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('realization_proof_images')
                            ->label('Bukti pembelian')
                            ->multiple()
                            ->minFiles(1)
                            ->maxFiles(12)
                            ->reorderable()
                            ->image()
                            ->directory('budget-requests/realization')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Minimal 1 gambar (kwitansi, invoice, foto barang).'),
                    ])
                    ->action(function (array $data, BudgetRequest $record): void {
                        try {
                            BudgetRealizationService::submit($record, $data);
                            Notification::make()
                                ->title('Realisasi berhasil')
                                ->body('Pengeluaran sudah masuk ke laporan keuangan.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal menyimpan realisasi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\ViewAction::make()
                    ->form(fn (BudgetRequest $record): array => [
                        Forms\Components\Section::make('Informasi Request Anggaran')
                            ->schema([
                                Forms\Components\TextInput::make('budget_name')
                                    ->label('Nama Anggaran')
                                    ->disabled(),
                                Forms\Components\Textarea::make('budget_detail')
                                    ->label('Detail Anggaran')
                                    ->disabled()
                                    ->rows(4)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('recipient_account')
                                    ->label('Rekening Penerima')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Nominal disetujui')
                                    ->disabled()
                                    ->prefix('Rp'),
                                Forms\Components\Placeholder::make('request_date_ph')
                                    ->label('Tanggal Request')
                                    ->content(fn () => $record->request_date?->format('d/m/Y') ?? '—'),
                                Forms\Components\TextInput::make('status_display')
                                    ->label('Status')
                                    ->disabled()
                                    ->default(function () use ($record) {
                                        if ($record->status === 'paid' && $record->realization_submitted_at === null) {
                                            return 'Paid — menunggu realisasi Anda';
                                        }
                                        if ($record->status === 'paid') {
                                            return 'Paid — realisasi sudah dikirim';
                                        }

                                        return match ($record->status) {
                                            'pending' => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            default => (string) $record->status,
                                        };
                                    }),
                            ])->columns(2),
                        Forms\Components\Section::make('Realisasi & bukti')
                            ->description('Tercatat setelah Anda mengirim realisasi.')
                            ->schema([
                                Forms\Components\Placeholder::make('realized_amount_ph')
                                    ->label('Nominal realisasi')
                                    ->content(fn () => $record->realized_amount !== null
                                        ? 'Rp '.number_format((float) $record->realized_amount, 0, ',', '.')
                                        : '—'),
                                Forms\Components\Placeholder::make('realization_notes_ph')
                                    ->label('Keterangan')
                                    ->content(fn () => $record->realization_notes ?: '—')
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('realization_at_ph')
                                    ->label('Dikirim pada')
                                    ->content(fn () => $record->realization_submitted_at
                                        ? $record->realization_submitted_at->format('d/m/Y H:i')
                                        : '—'),
                                Forms\Components\View::make('filament.infolists.components.budget-realization-proofs')
                                    ->label('Bukti pembelian')
                                    ->viewData([
                                        'proofs' => $record->realization_proof_images ?? [],
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->visible(fn () => $record->realization_submitted_at !== null),
                    ]),
            ]);
    }
}
