<?php

namespace App\Filament\Employee\Pages;

use App\Models\Assembly;
use App\Models\Component;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyAssembly extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.employee.pages.my-assembly';
    protected static ?string $navigationLabel = 'Assembly';
    protected static ?string $title = 'Daftar Assembly';

    public function table(Table $table): Table
    {
        return $table
            ->query(Assembly::query())
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->label('Produk')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assembly_date')
                    ->label('Tanggal Assembly')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->options([
                        'Macan' => 'DigiGate Macan',
                        'Maleo' => 'DigiGate Maleo',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\Select::make('invoice_id')
                            ->relationship(
                                name: 'invoice',
                                titleAttribute: 'invoice_number',
                                modifyQueryUsing: fn (Builder $query) => $query->where('status', 'paid')
                            )
                            ->label('Pilih Invoice (Paid)')
                            ->preload()
                            ->searchable()
                            ->required(),
                        
                        Forms\Components\Select::make('product_type')
                            ->label('Produk Yang Dirakit')
                            ->options([
                                'Macan' => 'DigiGate Macan (i7 11700K)',
                                'Maleo' => 'DigiGate Maleo (i7 8700K)',
                            ])
                            ->live()
                            ->required(),
                        
                        Forms\Components\DatePicker::make('assembly_date')
                            ->label('Tanggal Assembly')
                            ->default(now())
                            ->required(),

                        Forms\Components\Section::make('Pilih Serial Number Komponen')
                            ->description('Hanya menampilkan SN yang tersedia di stok')
                            ->schema(function (Forms\Get $get) {
                                $type = $get('product_type');
                                if (!$type) return [];

                                // Tentukan spek berdasarkan tipe
                                $procModel = ($type === 'Macan') ? 'Processor i7 11700K' : 'Processor i7 8700K';
                                $chassisModel = ($type === 'Macan') ? 'Chassis Macan' : 'Chassis Maleo';

                                return [
                                    // Dropdown Chassis
                                    Forms\Components\Select::make('sn_details.chassis')
                                        ->label("SN $chassisModel")
                                        ->options(fn() => Component::where('name', $chassisModel)->where('status', 'available')->pluck('sn', 'sn'))
                                        ->required(),
                                    
                                    // Dropdown Processor
                                    Forms\Components\Select::make('sn_details.processor')
                                        ->label("SN $procModel")
                                        ->options(fn() => Component::where('name', $procModel)->where('status', 'available')->pluck('sn', 'sn'))
                                        ->required(),
                                    
                                    // Dropdown RAM 1
                                    Forms\Components\Select::make('sn_details.ram_1')
                                        ->label('SN RAM DDR4 (Slot 1)')
                                        ->options(fn() => Component::where('name', 'RAM DDR4')->where('status', 'available')->pluck('sn', 'sn'))
                                        ->required(),

                                    // Dropdown RAM 2
                                    Forms\Components\Select::make('sn_details.ram_2')
                                        ->label('SN RAM DDR4 (Slot 2)')
                                        ->options(fn() => Component::where('name', 'RAM DDR4')->where('status', 'available')->pluck('sn', 'sn'))
                                        ->required(),

                                    // Dropdown SSD
                                    Forms\Components\Select::make('sn_details.ssd')
                                        ->label('SN SSD')
                                        ->options(fn() => Component::where('name', 'SSD')->where('status', 'available')->pluck('sn', 'sn'))
                                        ->required(),
                                ];
                            })->columns(2)
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Generate Serial Number dengan format DG(YYYY)(MM)(XXX)
                        $assemblyDate = $data['assembly_date'] ?? now();
                        $year = date('Y', strtotime($assemblyDate));
                        $month = date('m', strtotime($assemblyDate));
                        
                        // Hitung jumlah assembly di bulan ini berdasarkan assembly_date
                        $countThisMonth = Assembly::whereYear('assembly_date', $year)
                            ->whereMonth('assembly_date', (int)$month)
                            ->count();
                        
                        // Nomor urut = count + 1 (karena ini akan jadi assembly berikutnya)
                        $sequence = $countThisMonth + 1;
                        
                        // Format: DG202512001 (DG + YYYY + MM + XXX dengan padding 3 digit)
                        $data['serial_number'] = 'DG' . $year . $month . str_pad($sequence, 3, '0', STR_PAD_LEFT);
                        
                        return $data;
                    })
                    ->after(function (Assembly $record) {
                        // Update component status to 'used'
                        $data = $record->sn_details;
                        $serialNumbers = array_values($data);
                        Component::whereIn('sn', $serialNumbers)->update(['status' => 'used']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}

