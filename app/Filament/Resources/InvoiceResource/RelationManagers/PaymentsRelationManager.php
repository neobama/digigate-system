<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Services\InvoicePaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat Pembayaran';

    protected static ?string $recordTitleAttribute = 'amount';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')
                ->label('Nominal')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(0.01),
            Forms\Components\DatePicker::make('paid_at')
                ->label('Tanggal Bayar')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('method')
                ->label('Metode')
                ->options([
                    'transfer' => 'Transfer',
                    'cash' => 'Tunai',
                    'giro' => 'Giro/Cek',
                    'other' => 'Lainnya',
                ])
                ->nullable(),
            Forms\Components\TextInput::make('reference')
                ->label('No. Referensi')
                ->maxLength(100),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label('Tanggal')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('method')->label('Metode')->placeholder('-'),
                Tables\Columns\TextColumn::make('reference')->label('Referensi')->placeholder('-'),
                Tables\Columns\TextColumn::make('notes')->label('Catatan')->limit(40)->placeholder('-'),
                Tables\Columns\TextColumn::make('creator.name')->label('Dicatat oleh')->placeholder('-'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Catat Pembayaran')
                    ->visible(fn () => in_array($this->getOwnerRecord()->status, ['proforma', 'partially_paid', 'paid'], true)
                        && $this->getOwnerRecord()->remainingAmount() > 0.009)
                    ->mutateFormDataUsing(function (array $data): array {
                        $invoice = $this->getOwnerRecord();
                        $remaining = $invoice->remainingAmount();
                        $data['amount'] = min((float) $data['amount'], $remaining);

                        return $data;
                    })
                    ->using(function (array $data): Model {
                        return app(InvoicePaymentService::class)->recordPayment(
                            $this->getOwnerRecord(),
                            $data
                        );
                    })
                    ->successNotificationTitle('Pembayaran tercatat'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'delivered')
                    ->action(function (Model $record) {
                        try {
                            app(InvoicePaymentService::class)->deletePayment($record);
                            Notification::make()->success()->title('Pembayaran dihapus')->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Catat DP atau cicilan di sini. Status otomatis jadi Partially Paid / Paid.');
    }
}
