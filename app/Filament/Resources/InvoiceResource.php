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
                            ->placeholder('Semua Bulan')
                            ->options(function () {
                                $months = [];
                                for ($i = 1; $i <= 12; $i++) {
                                    $months[$i] = date('F', mktime(0, 0, 0, $i, 1));
                                }
                                return $months;
                            }),
                        Forms\Components\Select::make('year')
                            ->label('Tahun')
                            ->placeholder('Semua Tahun')
                            ->options(function () {
                                $years = [];
                                $currentYear = now()->year;
                                for ($i = $currentYear - 5; $i <= $currentYear + 1; $i++) {
                                    $years[$i] = $i;
                                }
                                return $years;
                            }),
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
            ->defaultSort('invoice_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                // Action cepat untuk mengubah Proforma menjadi Paid
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Set Lunas')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Invoice $record) => $record->status !== 'proforma')
                    ->action(fn (Invoice $record) => $record->update(['status' => 'paid', 'paid_date' => now()])),
                Tables\Actions\Action::make('markAsDelivered')
                    ->label('Set Delivered')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->hidden(fn (Invoice $record) => $record->status !== 'paid')
                    ->action(function (Invoice $record) {
                        // If paid_date is not set, set it now (in case status changed directly)
                        $updateData = ['status' => 'delivered'];
                        if (!$record->paid_date) {
                            $updateData['paid_date'] = now();
                        }
                        $record->update($updateData);
                    }),
                Tables\Actions\Action::make('generateProformaPdf')
                    ->label('Generate Proforma Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->url(fn (Invoice $record) => route('invoices.proforma.pdf', $record))
                    ->openUrlInNewTab()
                    ->hidden(function (Invoice $record) {
                        try {
                            if ($record->status !== 'proforma') {
                                return true;
                            }
                            // Hide if document already exists
                            return \App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'invoice')
                                ->where(function($query) use ($record) {
                                    $query->where('name', 'like', "%Proforma%{$record->invoice_number}%")
                                          ->orWhere('file_name', 'like', "proforma-{$record->invoice_number}.pdf");
                                })
                                ->exists();
                        } catch (\Exception $e) {
                            \Log::error('Error in generateProformaPdf hidden: ' . $e->getMessage());
                            return false;
                        }
                    }),
                Tables\Actions\Action::make('viewProformaDocument')
                    ->label('View Proforma Invoice')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Invoice $record) => 'Proforma Invoice #' . $record->invoice_number)
                    ->modalContent(function (Invoice $record) {
                        $document = \App\Models\Document::where('related_invoice_id', $record->id)
                            ->where('category', 'invoice')
                            ->where(function($query) use ($record) {
                                $query->where('name', 'like', "%Proforma%{$record->invoice_number}%")
                                      ->orWhere('file_name', 'like', "proforma-{$record->invoice_number}.pdf");
                            })
                            ->first();
                        
                        if ($document) {
                            $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                            return view('filament.documents.preview', [
                                'document' => $document,
                                'fileUrl' => \Illuminate\Support\Facades\Storage::disk($disk)->url($document->file_path),
                            ]);
                        }
                        return view('filament.invoices.no-document', ['invoice' => $record]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->hidden(function (Invoice $record) {
                        try {
                            if ($record->status !== 'proforma') {
                                return true;
                            }
                            // Show only if document exists
                            $exists = \App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'invoice')
                                ->where(function($query) use ($record) {
                                    $query->where('name', 'like', "%Proforma%{$record->invoice_number}%")
                                          ->orWhere('file_name', 'like', "proforma-{$record->invoice_number}.pdf");
                                })
                                ->exists();
                            return !$exists;
                        } catch (\Exception $e) {
                            \Log::error('Error in viewProformaDocument hidden: ' . $e->getMessage());
                            return true;
                        }
                    }),
                Tables\Actions\Action::make('generateInvoicePdf')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (Invoice $record) => route('invoices.paid.pdf', $record))
                    ->openUrlInNewTab()
                    ->hidden(function (Invoice $record) {
                        try {
                            if (!in_array($record->status, ['paid', 'delivered'])) {
                                return true;
                            }
                            // Hide if document already exists
                            $exists = \App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'invoice')
                                ->where(function($query) use ($record) {
                                    $query->where(function($q) use ($record) {
                                        $q->where('name', 'like', "%Invoice {$record->invoice_number}%")
                                          ->where('name', 'not like', '%Proforma%');
                                    })
                                    ->orWhere('file_name', 'like', "invoice-{$record->invoice_number}.pdf");
                                })
                                ->exists();
                            return $exists;
                        } catch (\Exception $e) {
                            \Log::error('Error in generateInvoicePdf hidden: ' . $e->getMessage());
                            return false;
                        }
                    }),
                Tables\Actions\Action::make('viewInvoiceDocument')
                    ->label('View Invoice')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Invoice $record) => 'Invoice #' . $record->invoice_number)
                    ->modalContent(function (Invoice $record) {
                        $document = \App\Models\Document::where('related_invoice_id', $record->id)
                            ->where('category', 'invoice')
                            ->where(function($query) use ($record) {
                                $query->where('name', 'like', "%Invoice {$record->invoice_number}%")
                                      ->where('name', 'not like', '%Proforma%')
                                      ->orWhere('file_name', 'like', "invoice-{$record->invoice_number}.pdf");
                            })
                            ->first();
                        
                        if ($document) {
                            $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                            return view('filament.documents.preview', [
                                'document' => $document,
                                'fileUrl' => \Illuminate\Support\Facades\Storage::disk($disk)->url($document->file_path),
                            ]);
                        }
                        return view('filament.invoices.no-document', ['invoice' => $record]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->hidden(function (Invoice $record) {
                        try {
                            if (!in_array($record->status, ['paid', 'delivered'])) {
                                return true;
                            }
                            // Show only if document exists
                            $exists = \App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'invoice')
                                ->where(function($query) use ($record) {
                                    $query->where(function($q) use ($record) {
                                        $q->where('name', 'like', "%Invoice {$record->invoice_number}%")
                                          ->where('name', 'not like', '%Proforma%');
                                    })
                                    ->orWhere('file_name', 'like', "invoice-{$record->invoice_number}.pdf");
                                })
                                ->exists();
                            return !$exists;
                        } catch (\Exception $e) {
                            \Log::error('Error in viewInvoiceDocument hidden: ' . $e->getMessage());
                            return true;
                        }
                    }),
                Tables\Actions\Action::make('generateSuratJalan')
                    ->label('Generate Surat Jalan')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->url(fn (Invoice $record) => route('invoices.surat-jalan.pdf', $record))
                    ->openUrlInNewTab()
                    ->hidden(function (Invoice $record) {
                        try {
                            if ($record->status !== 'delivered') {
                                return true;
                            }
                            // Hide if document already exists
                            return \App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'surat_jalan')
                                ->exists();
                        } catch (\Exception $e) {
                            \Log::error('Error in generateSuratJalan hidden: ' . $e->getMessage());
                            return false;
                        }
                    }),
                Tables\Actions\Action::make('viewSuratJalan')
                    ->label('View Surat Jalan')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Invoice $record) => 'Surat Jalan - Invoice #' . $record->invoice_number)
                    ->modalContent(function (Invoice $record) {
                        $document = \App\Models\Document::where('related_invoice_id', $record->id)
                            ->where('category', 'surat_jalan')
                            ->first();
                        
                        if ($document) {
                            $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                            return view('filament.documents.preview', [
                                'document' => $document,
                                'fileUrl' => \Illuminate\Support\Facades\Storage::disk($disk)->url($document->file_path),
                            ]);
                        }
                        return view('filament.invoices.no-document', ['invoice' => $record]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->hidden(function (Invoice $record) {
                        try {
                            if ($record->status !== 'delivered') {
                                return true;
                            }
                            // Show only if document exists
                            return !\App\Models\Document::where('related_invoice_id', $record->id)
                                ->where('category', 'surat_jalan')
                                ->exists();
                        } catch (\Exception $e) {
                            \Log::error('Error in viewSuratJalan hidden: ' . $e->getMessage());
                            return true;
                        }
                    }),
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
                                // Get file size
                                $fileSize = \Illuminate\Support\Facades\Storage::disk($disk)->size($filePath);
                                
                                // Get mime type
                                try {
                                    $mimeType = \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($filePath);
                                } catch (\Exception $e) {
                                    // Fallback: determine mime type from extension
                                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                                    $mimeType = match(strtolower($extension)) {
                                        'pdf' => 'application/pdf',
                                        'doc' => 'application/msword',
                                        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'xls' => 'application/vnd.ms-excel',
                                        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'jpg', 'jpeg' => 'image/jpeg',
                                        'png' => 'image/png',
                                        'gif' => 'image/gif',
                                        'zip' => 'application/zip',
                                        'rar' => 'application/x-rar-compressed',
                                        default => 'application/octet-stream',
                                    };
                                }
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
