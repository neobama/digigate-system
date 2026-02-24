<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetRequestResource\Pages;
use App\Models\BudgetRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class BudgetRequestResource extends Resource
{
    protected static ?string $model = BudgetRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Request Anggaran';
    protected static ?string $navigationGroup = 'HR';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Request Anggaran')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Karyawan')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('budget_name')
                            ->label('Nama Anggaran')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('budget_detail')
                            ->label('Detail Anggaran')
                            ->required()
                            ->rows(4)
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
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'paid' => 'Paid',
                            ])
                            ->default('pending')
                            ->required()
                            ->visibleOn('edit'),
                        Forms\Components\FileUpload::make('proof_of_payment')
                            ->label('Bukti Pembayaran')
                            ->image()
                            ->directory('budget-requests/proof')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/*'])
                            ->helperText('Upload bukti pembayaran saat status Paid (maks 5MB)')
                            ->visibleOn('edit'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Tanggal Paid')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visible(fn ($record) => $record->status === 'paid' && !empty($record->paid_at)),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
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
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BudgetRequest $record) => $record->status === 'pending')
                    ->action(function (BudgetRequest $record) {
                        $record->update(['status' => 'approved']);
                    })
                    ->successNotificationTitle('Request anggaran approved'),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BudgetRequest $record) => $record->status === 'pending')
                    ->action(function (BudgetRequest $record) {
                        $record->update(['status' => 'rejected']);
                    })
                    ->successNotificationTitle('Request anggaran rejected'),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (BudgetRequest $record) => $record->status === 'approved')
                    ->action(function (BudgetRequest $record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Request anggaran marked as paid'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Request Anggaran')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Nama Karyawan'),
                        Infolists\Components\TextEntry::make('employee.nik')
                            ->label('NIK'),
                        Infolists\Components\TextEntry::make('budget_name')
                            ->label('Nama Anggaran'),
                        Infolists\Components\TextEntry::make('budget_detail')
                            ->label('Detail Anggaran')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('recipient_account')
                            ->label('Rekening Penerima'),
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Nominal Anggaran')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('request_date')
                            ->label('Tanggal Request')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'paid' => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Tanggal Paid')
                            ->dateTime('d/m/Y H:i')
                            ->visible(fn ($record) => $record->status === 'paid' && !empty($record->paid_at)),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2),
                Infolists\Components\Section::make('Invoice')
                    ->schema([
                        Infolists\Components\ViewEntry::make('invoice')
                            ->label('')
                            ->view('filament.infolists.components.budget-request-invoice')
                            ->viewData(function (BudgetRequest $record) {
                                return [
                                    'invoice' => $record->invoice,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BudgetRequest $record) => !empty($record->invoice)),
                Infolists\Components\Section::make('Bukti Pembayaran')
                    ->schema([
                        Infolists\Components\ViewEntry::make('proof_of_payment')
                            ->label('')
                            ->view('filament.infolists.components.reimbursement-proof')
                            ->viewData(function (BudgetRequest $record) {
                                return [
                                    'proof' => $record->proof_of_payment,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BudgetRequest $record) => !empty($record->proof_of_payment)),
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
            'index' => Pages\ListBudgetRequests::route('/'),
            'view' => Pages\ViewBudgetRequest::route('/{record}'),
            'edit' => Pages\EditBudgetRequest::route('/{record}/edit'),
        ];
    }
}
