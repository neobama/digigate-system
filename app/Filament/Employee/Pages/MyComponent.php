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
                        'RAM DDR4' => 'RAM DDR4',
                        'SSD' => 'SSD',
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
                            ->description('Upload invoice untuk auto-detect semua items. Fitur experimental.')
                            ->schema([
                                Forms\Components\FileUpload::make('invoice_image')
                                    ->label('Upload Invoice')
                                    ->image()
                                    ->maxSize(5120)
                                    ->acceptedFileTypes(['image/*'])
                                    ->helperText('Upload invoice untuk auto-detect items dari invoice')
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            try {
                                                $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                                                $imagePath = is_array($state) ? $state[0] : $state;
                                                
                                                // Get image content - handle both temporary and stored files
                                                if (Storage::disk($disk)->exists($imagePath)) {
                                                    $imageContent = Storage::disk($disk)->get($imagePath);
                                                } else {
                                                    // Try public disk
                                                    if (Storage::disk('public')->exists($imagePath)) {
                                                        $imageContent = Storage::disk('public')->get($imagePath);
                                                    } else {
                                                        // Try to get from full path
                                                        $fullPath = storage_path('app/public/' . $imagePath);
                                                        if (file_exists($fullPath)) {
                                                            $imageContent = file_get_contents($fullPath);
                                                        } else {
                                                            throw new \Exception('File tidak ditemukan');
                                                        }
                                                    }
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
                                        }
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->badge('Experimental'),
                        Forms\Components\Repeater::make('components')
                            ->label('Daftar Komponen')
                            ->schema([
                                Forms\Components\Select::make('name')
                                    ->label('Tipe Komponen')
                                    ->options([
                                        'Processor i7 11700K' => 'Processor i7 11700K',
                                        'Processor i7 8700K' => 'Processor i7 8700K',
                                        'RAM DDR4' => 'RAM DDR4',
                                        'SSD' => 'SSD',
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
                                'RAM DDR4' => 'RAM DDR4',
                                'SSD' => 'SSD',
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
                                'RAM DDR4' => 'RAM DDR4',
                                'SSD' => 'SSD',
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

