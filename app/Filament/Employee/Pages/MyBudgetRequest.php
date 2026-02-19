<?php

namespace App\Filament\Employee\Pages;

use App\Models\BudgetRequest;
use Filament\Forms;
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
                        if (!$state) {
                            return '-';
                        }
                        return 'Lihat Invoice';
                    })
                    ->url(fn ($record) => $record->invoice ? Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($record->invoice) : null)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-document')
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'paid',
                    ])
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                        default => $state,
                    }),
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
                                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                                    ->maxSize(5120) // 5MB
                                    ->helperText('Upload invoice jika ada (maks 5MB)'),
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
                Tables\Actions\ViewAction::make()
                    ->form([
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
                                    ->label('Nominal Anggaran')
                                    ->disabled()
                                    ->prefix('Rp'),
                                Forms\Components\TextInput::make('request_date')
                                    ->label('Tanggal Request')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->label('Status')
                                    ->disabled(),
                            ])->columns(2),
                    ]),
            ]);
    }
}
