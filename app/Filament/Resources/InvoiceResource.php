<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Operational';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Nomor Invoice')
                        ->default('INV-' . date('Ymd') . '-' . rand(100, 999))
                        ->required()
                        ->readonly(),
                    Forms\Components\TextInput::make('client_name')
                        ->label('Nama Client')
                        ->required(),
                    Forms\Components\TextInput::make('po_number')
                        ->label('Nomor PO (Optional)'),
                    Forms\Components\DatePicker::make('invoice_date')
                        ->label('Tanggal Invoice')
                        ->default(now())
                        ->required(),
                    Forms\Components\Repeater::make('items')
                        ->label('Daftar Perangkat')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Perangkat')
                                ->required(),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required(),
                            Forms\Components\TextInput::make('price')
                                ->label('Harga')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),
                        ])
                        ->collapsible()
                        ->grid(2),
                    Forms\Components\TextInput::make('discount')
                        ->label('Diskon (Optional)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->nullable(),
                    Forms\Components\TextInput::make('shipping_cost')
                        ->label('Ongkir (Optional)')
                        ->numeric()
                        ->prefix('Rp')
                        ->nullable(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'proforma' => 'Proforma (Belum Bayar)',
                            'paid' => 'Paid (Lunas)',
                            'delivered' => 'Delivered (Sudah Dikirim)',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('proforma')
                        ->required(),
                ])->columns(2)
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable(),
                Tables\Columns\TextColumn::make('client_name')->searchable(),
                Tables\Columns\TextColumn::make('total_amount')->money('IDR'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'proforma',
                        'success' => 'paid',
                        'info' => 'delivered',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('invoice_date')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'proforma' => 'Proforma',
                        'paid' => 'Paid',
                        'delivered' => 'Delivered',
                    ]),
                Tables\Filters\Filter::make('invoice_date')
                    ->form([
                        Forms\Components\Select::make('month')
                            ->label('Bulan')
                            ->options(function () {
                                $months = [];
                                for ($i = 1; $i <= 12; $i++) {
                                    $months[$i] = date('F', mktime(0, 0, 0, $i, 1));
                                }
                                return $months;
                            })
                            ->default(now()->month),
                        Forms\Components\Select::make('year')
                            ->label('Tahun')
                            ->options(function () {
                                $years = [];
                                $currentYear = now()->year;
                                for ($i = $currentYear - 5; $i <= $currentYear + 1; $i++) {
                                    $years[$i] = $i;
                                }
                                return $years;
                            })
                            ->default(now()->year),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['month'] && $data['year'],
                                fn (Builder $query, $date): Builder => $query->whereMonth('invoice_date', $data['month'])
                                    ->whereYear('invoice_date', $data['year']),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Action cepat untuk mengubah Proforma menjadi Paid
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Lunas')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Invoice $record) => $record->status !== 'proforma')
                    ->action(fn (Invoice $record) => $record->update(['status' => 'paid']))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('markAsDelivered')
                    ->label('Set Delivered')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->hidden(fn (Invoice $record) => $record->status !== 'paid')
                    ->action(fn (Invoice $record) => $record->update(['status' => 'delivered']))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('downloadProformaPdf')
                    ->label('PDF Proforma')
                    ->icon('heroicon-o-document-text')
                    ->color('secondary')
                    ->hidden(fn (Invoice $record) => $record->status !== 'proforma')
                    ->url(fn (Invoice $record) => route('invoices.proforma.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('downloadPaidPdf')
                    ->label('PDF Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->hidden(fn (Invoice $record) => ! in_array($record->status, ['paid', 'delivered']))
                    ->url(fn (Invoice $record) => route('invoices.paid.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('uploadDocument')
                    ->label('Upload Dokumen')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Dokumen')
                            ->required()
                            ->default(fn (Invoice $record) => 'Invoice ' . $record->invoice_number),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2),
                        Forms\Components\Select::make('category')
                            ->label('Kategori')
                            ->options([
                                'invoice' => 'Invoice',
                                'contract' => 'Kontrak',
                                'certificate' => 'Sertifikat',
                                'other' => 'Lainnya',
                            ])
                            ->default('invoice'),
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->directory('documents')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/*',
                            ])
                            ->maxSize(10240)
                            ->required(),
                    ])
                    ->action(function (array $data, Invoice $record) {
                        $filePath = $data['file_path'];
                        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                        
                        // Get file info
                        $fileSize = 0;
                        $mimeType = 'application/octet-stream';
                        try {
                            if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($filePath)) {
                                $fileInfo = \Illuminate\Support\Facades\Storage::disk($disk)->getMetadata($filePath);
                                $mimeType = $fileInfo['mimetype'] ?? 'application/octet-stream';
                                $fileSize = $fileInfo['size'] ?? 0;
                            }
                        } catch (\Exception $e) {
                            // Fallback
                        }
                        
                        \App\Models\Document::create([
                            'name' => $data['name'],
                            'file_path' => $filePath,
                            'file_name' => basename($filePath),
                            'mime_type' => $mimeType,
                            'file_size' => $fileSize,
                            'category' => $data['category'] ?? 'invoice',
                            'description' => $data['description'] ?? null,
                            'related_invoice_id' => $record->id,
                            'uploaded_by' => auth()->id(),
                            'access_level' => 'private',
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Dokumen berhasil diupload')
                            ->body('Dokumen telah ditambahkan dan terhubung dengan invoice ini.')
                            ->send();
                    }),
            ])
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Export Semua')
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn () => 'invoices-' . date('Y-m-d-His')),
                    ]),
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Export Bulan Ini')
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->modifyQueryUsing(fn ($query) => $query->whereMonth('invoice_date', now()->month)
                                ->whereYear('invoice_date', now()->year))
                            ->withFilename(fn () => 'invoices-' . now()->format('Y-m') . '-' . date('His')),
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
