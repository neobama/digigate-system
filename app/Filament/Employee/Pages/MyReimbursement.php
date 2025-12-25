<?php

namespace App\Filament\Employee\Pages;

use App\Models\Reimbursement;
use App\Services\GeminiService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MyReimbursement extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.employee.pages.my-reimbursement';
    protected static ?string $navigationLabel = 'Reimbursement';
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
                    ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                    ->url(fn ($record) => $record->proof_of_payment ? \Storage::disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')->url($record->proof_of_payment) : null)
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
                        Forms\Components\Section::make('Experimental AI - Auto Fill dari Invoice')
                            ->description('⚠️ Upload invoice/bon untuk auto-fill form (Experimental Feature)')
                            ->schema([
                                Forms\Components\FileUpload::make('ai_invoice_image')
                                    ->label('Upload Invoice/Bon untuk AI Parse')
                                    ->image()
                                    ->maxSize(5120)
                                    ->acceptedFileTypes(['image/*'])
                                    ->helperText('Upload invoice/bon untuk auto-fill form. Fitur experimental.')
                                    ->dehydrated(false) // Jangan simpan ke storage, hanya untuk parsing
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get, $component) {
                                        if (!$state) {
                                            return;
                                        }
                                        
                                        try {
                                            // Get file from temporary upload (Livewire)
                                            $file = is_array($state) ? $state[0] : $state;
                                            
                                            $imageContent = null;
                                            
                                            // Handle TemporaryUploadedFile from Livewire
                                            if ($file instanceof TemporaryUploadedFile) {
                                                // Read directly from temporary file using getRealPath or get()
                                                try {
                                                    $realPath = $file->getRealPath();
                                                    if ($realPath && file_exists($realPath)) {
                                                        $imageContent = file_get_contents($realPath);
                                                    } else {
                                                        // Fallback to get() method
                                                        $imageContent = $file->get();
                                                    }
                                                } catch (\Exception $e) {
                                                    // Try get() method directly
                                                    $imageContent = $file->get();
                                                }
                                            } elseif (is_string($file)) {
                                                // Handle string path - try multiple locations
                                                $paths = [
                                                    storage_path('app/livewire-tmp/' . basename($file)),
                                                    storage_path('app/public/livewire-tmp/' . basename($file)),
                                                    storage_path('app/livewire-tmp/' . $file),
                                                    storage_path('app/public/livewire-tmp/' . $file),
                                                    $file, // Direct path
                                                ];
                                                
                                                foreach ($paths as $path) {
                                                    if (file_exists($path) && is_file($path)) {
                                                        $imageContent = file_get_contents($path);
                                                        if ($imageContent) break;
                                                    }
                                                }
                                                
                                                // If still not found, try reading from Storage
                                                if (!$imageContent) {
                                                    $storagePaths = [
                                                        'livewire-tmp/' . basename($file),
                                                        'livewire-tmp/' . $file,
                                                    ];
                                                    
                                                    foreach ($storagePaths as $storagePath) {
                                                        try {
                                                            if (Storage::disk('local')->exists($storagePath)) {
                                                                $imageContent = Storage::disk('local')->get($storagePath);
                                                                break;
                                                            }
                                                        } catch (\Exception $e) {
                                                            continue;
                                                        }
                                                    }
                                                }
                                            } else {
                                                throw new \Exception('Format file tidak didukung: ' . gettype($file));
                                            }
                                            
                                            if (!$imageContent) {
                                                Notification::make()
                                                    ->warning()
                                                    ->title('File tidak ditemukan')
                                                    ->body('Silakan upload ulang file.')
                                                    ->send();
                                                return;
                                            }
                                            
                                            $imageBase64 = base64_encode($imageContent);
                                            
                                            // Call Gemini API
                                            $gemini = new GeminiService();
                                            $parsed = $gemini->parseReimbursementInvoice($imageBase64);
                                            
                                            if ($parsed) {
                                                // Auto-fill form
                                                if (!empty($parsed['purpose'])) {
                                                    $set('purpose', $parsed['purpose']);
                                                }
                                                if (!empty($parsed['expense_date'])) {
                                                    $set('expense_date', $parsed['expense_date']);
                                                }
                                                if (!empty($parsed['amount'])) {
                                                    $set('amount', $parsed['amount']);
                                                }
                                                if (!empty($parsed['description'])) {
                                                    $set('description', $parsed['description']);
                                                }
                                                
                                                // Note: proof_of_payment akan diisi user secara terpisah
                                                
                                                Notification::make()
                                                    ->success()
                                                    ->title('AI Parse Berhasil')
                                                    ->body('Form telah diisi otomatis. Silakan periksa dan edit jika diperlukan.')
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->warning()
                                                    ->title('AI Parse Gagal')
                                                    ->body('Tidak dapat memparse invoice. Silakan isi form secara manual.')
                                                    ->send();
                                            }
                                        } catch (\Exception $e) {
                                            \Log::error('Reimbursement AI Parse Error', [
                                                'error' => $e->getMessage(),
                                                'trace' => $e->getTraceAsString()
                                            ]);
                                            
                                            Notification::make()
                                                ->danger()
                                                ->title('Error')
                                                ->body('Terjadi error saat parsing: ' . $e->getMessage())
                                                ->send();
                                        }
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(),
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
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/*'])
                            ->required()
                            ->helperText('Upload bukti pembayaran (maks 5MB, format: JPG, PNG)'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = auth()->user()->employee?->id;
                        $data['status'] = 'pending';
                        return $data;
                    })
                    ->after(function (Reimbursement $record) {
                        // Move file to S3 if configured
                        if (config('filesystems.default') === 's3' && !empty($record->proof_of_payment)) {
                            if (Storage::disk('public')->exists($record->proof_of_payment)) {
                                $content = Storage::disk('public')->get($record->proof_of_payment);
                                $s3Path = Storage::disk('s3_public')->put($record->proof_of_payment, $content, 'public');
                                if ($s3Path) {
                                    $record->update(['proof_of_payment' => $s3Path]);
                                    Storage::disk('public')->delete($record->proof_of_payment); // Delete from local
                                }
                            }
                        }
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
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/*']),
                    ]),
            ]);
    }
}

