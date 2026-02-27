<?php

namespace App\Filament\Employee\Pages;

use App\Models\Component;
use App\Services\GeminiService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MyComponent extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static string $view = 'filament.employee.pages.my-component';
    protected static ?string $navigationLabel = 'Component';
    protected static ?string $title = 'Component List';

    public function table(Table $table): Table
    {
        return $table
            ->query(Component::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tipe Komponen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('sn')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Tanggal Pembelian')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'available',
                        'danger' => 'used',
                        'warning' => 'warranty_claim',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Tersedia',
                        'used' => 'Terpakai',
                        'warranty_claim' => 'Klaim Garansi',
                    ]),
                Tables\Filters\SelectFilter::make('name')
                    ->label('Tipe Komponen')
                    ->options([
                        'Processor i7 11700K' => 'Processor i7 11700K',
                        'Processor i7 8700K' => 'Processor i7 8700K',
                        'Processor i7 14700K' => 'Processor i7 14700K',
                        'RAM DDR4' => 'RAM DDR4',
                        'RAM DDR5' => 'RAM DDR5',
                        'SSD' => 'SSD',
                        'Chassis Macan' => 'Chassis Macan',
                        'Chassis Maleo' => 'Chassis Maleo',
                        'Chassis Komodo' => 'Chassis Komodo',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('bulkAdd')
                    ->label('Bulk Add (Experimental AI)')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->badge('Experimental')
                    ->form([
                        Forms\Components\Section::make('Upload Invoice untuk AI Parse')
                            ->description('⚠️ Upload invoice untuk auto-detect semua items. Fitur experimental.')
                            ->schema([
                                Forms\Components\FileUpload::make('invoice_image')
                                    ->label('Upload Invoice')
                                    ->image()
                                    ->maxSize(5120)
                                    ->acceptedFileTypes(['image/*'])
                                    ->helperText('Upload invoice untuk auto-detect items dari invoice')
                                    ->dehydrated(false) // Jangan simpan ke storage, hanya untuk parsing
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
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
                                            $parsedItems = $gemini->parseComponentInvoice($imageBase64);
                                            
                                            if ($parsedItems && count($parsedItems) > 0) {
                                                // Convert to Repeater format
                                                $components = [];
                                                foreach ($parsedItems as $item) {
                                                    // Handle quantity - create multiple entries if quantity > 1
                                                    $quantity = $item['quantity'] ?? 1;
                                                    for ($i = 0; $i < $quantity; $i++) {
                                                        $components[] = [
                                                            'name' => $item['name'] ?? null,
                                                            'supplier' => $item['supplier'] ?? null,
                                                            'purchase_date' => $item['purchase_date'] ?? now()->format('Y-m-d'),
                                                            'sn' => '', // User must fill
                                                            'status' => 'available',
                                                        ];
                                                    }
                                                }
                                                
                                                $set('components', $components);
                                                
                                                Notification::make()
                                                    ->success()
                                                    ->title('AI Parse Berhasil')
                                                    ->body('Ditemukan ' . count($components) . ' item(s). Silakan lengkapi Serial Number untuk setiap item.')
                                                    ->send();
                                            } else {
                                                Notification::make()
                                                    ->warning()
                                                    ->title('AI Parse Gagal')
                                                    ->body('Tidak dapat memparse invoice atau tidak ada item ditemukan. Silakan tambah manual.')
                                                    ->send();
                                            }
                                        } catch (\Exception $e) {
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
                        Forms\Components\Repeater::make('components')
                            ->label('Daftar Komponen')
                            ->schema([
                                Forms\Components\Select::make('name')
                                    ->label('Tipe Komponen')
                                    ->options([
                                        'Processor i7 11700K' => 'Processor i7 11700K',
                                        'Processor i7 8700K' => 'Processor i7 8700K',
                                        'Processor i7 14700K' => 'Processor i7 14700K',
                                        'RAM DDR4' => 'RAM DDR4',
                                        'RAM DDR5' => 'RAM DDR5',
                                        'SSD' => 'SSD',
                                        'Chassis Macan' => 'Chassis Macan',
                                        'Chassis Maleo' => 'Chassis Maleo',
                                        'Chassis Komodo' => 'Chassis Komodo',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('sn')
                                    ->label('Serial Number (SN)')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Wajib diisi untuk setiap komponen'),
                                Forms\Components\TextInput::make('supplier')
                                    ->label('Supplier')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Nomor Invoice')
                                    ->maxLength(255)
                                    ->placeholder('Nomor invoice pembelian komponen'),
                                Forms\Components\DatePicker::make('purchase_date')
                                    ->label('Tanggal Pembelian')
                                    ->required()
                                    ->displayFormat('d/m/Y'),
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'available' => 'Tersedia',
                                        'used' => 'Terpakai',
                                        'warranty_claim' => 'Klaim Garansi',
                                    ])
                                    ->default('available')
                                    ->required(),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Komponen Baru')
                            ->grid(2)
                            ->addActionLabel('Tambah Komponen')
                            ->reorderable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $created = 0;
                        $errors = [];
                        
                        foreach ($data['components'] ?? [] as $componentData) {
                            // Validate SN is unique
                            if (Component::where('sn', $componentData['sn'])->exists()) {
                                $errors[] = "SN {$componentData['sn']} sudah ada";
                                continue;
                            }
                            
                            try {
                                Component::create([
                                    'name' => $componentData['name'],
                                    'sn' => $componentData['sn'],
                                    'supplier' => $componentData['supplier'],
                                    'invoice_number' => $componentData['invoice_number'] ?? null,
                                    'purchase_date' => $componentData['purchase_date'],
                                    'status' => $componentData['status'] ?? 'available',
                                ]);
                                $created++;
                            } catch (\Exception $e) {
                                $errors[] = "Error: " . $e->getMessage();
                            }
                        }
                        
                        if ($created > 0) {
                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body("{$created} komponen berhasil ditambahkan.")
                                ->send();
                        }
                        
                        if (count($errors) > 0) {
                            Notification::make()
                                ->warning()
                                ->title('Ada Error')
                                ->body(implode(', ', $errors))
                                ->send();
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Bulk Add Berhasil')
                            ->body('Komponen berhasil ditambahkan.')
                    ),
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Komponen')
                    ->model(Component::class)
                    ->form([
                        Forms\Components\Select::make('name')
                            ->label('Tipe Komponen')
                            ->options([
                                'Processor i7 11700K' => 'Processor i7 11700K',
                                'Processor i7 8700K' => 'Processor i7 8700K',
                                'Processor i7 14700K' => 'Processor i7 14700K',
                                'RAM DDR4' => 'RAM DDR4',
                                'RAM DDR5' => 'RAM DDR5',
                                'SSD' => 'SSD',
                                'Chassis Macan' => 'Chassis Macan',
                                'Chassis Maleo' => 'Chassis Maleo',
                                'Chassis Komodo' => 'Chassis Komodo',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sn')
                            ->label('Serial Number (SN)')
                            ->unique(Component::class, 'sn', ignoreRecord: true)
                            ->required()
                            ->maxLength(255)
                            ->helperText('Masukkan serial number komponen'),
                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Nama supplier atau vendor'),
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->maxLength(255)
                            ->placeholder('Nomor invoice pembelian komponen'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->default(now())
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'available' => 'Tersedia',
                                'used' => 'Terpakai',
                                'warranty_claim' => 'Klaim Garansi',
                            ])
                            ->default('available')
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set default status jika tidak diisi
                        if (!isset($data['status'])) {
                            $data['status'] = 'available';
                        }
                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Komponen berhasil ditambahkan')
                            ->body('Komponen baru telah ditambahkan ke sistem.')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('name')
                            ->label('Tipe Komponen')
                            ->options([
                                'Processor i7 11700K' => 'Processor i7 11700K',
                                'Processor i7 8700K' => 'Processor i7 8700K',
                                'Processor i7 14700K' => 'Processor i7 14700K',
                                'RAM DDR4' => 'RAM DDR4',
                                'RAM DDR5' => 'RAM DDR5',
                                'SSD' => 'SSD',
                                'Chassis Macan' => 'Chassis Macan',
                                'Chassis Maleo' => 'Chassis Maleo',
                                'Chassis Komodo' => 'Chassis Komodo',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sn')
                            ->label('Serial Number (SN)')
                            ->unique(Component::class, 'sn', ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->maxLength(255)
                            ->placeholder('Nomor invoice pembelian komponen'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'available' => 'Tersedia',
                                'used' => 'Terpakai',
                                'warranty_claim' => 'Klaim Garansi',
                            ])
                            ->required(),
                    ])
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Komponen berhasil diupdate')
                            ->body('Data komponen telah diperbarui.')
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

