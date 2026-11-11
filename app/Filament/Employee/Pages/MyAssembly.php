<?php

namespace App\Filament\Employee\Pages;

use App\Models\Assembly;
use App\Models\Component;
use App\Models\Invoice;
use App\Services\AssemblyInvoiceValidator;
use App\Support\AssemblySerialNumberGenerator;
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
                    ->options(fn () => \App\Services\DeviceTypeCatalog::deviceTypeOptions(false)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\Select::make('invoice_id')
                            ->relationship(
                                name: 'invoice',
                                titleAttribute: 'invoice_number',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->whereIn('status', ['paid', 'delivered'])
                                    ->withCount('assemblies')
                            )
                            ->label('Pilih Invoice (Paid / Delivered)')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->disableOptionWhen(function ($value): bool {
                                if (! $value) {
                                    return false;
                                }

                                $invoice = Invoice::withCount('assemblies')->find($value);

                                return $invoice && ! $invoice->canCreateMoreAssemblies();
                            })
                            ->helperText('Invoice yang sudah selesai dirakit tidak bisa dipilih lagi.'),
                        
                        Forms\Components\Select::make('product_type')
                            ->label('Produk Yang Dirakit')
                            ->options(fn () => \App\Services\DeviceTypeCatalog::productTypeOptions())
                            ->live()
                            ->required()
                            ->helperText('Daftar dari tipe perangkat yang didefinisikan admin'),
                        
                        Forms\Components\DatePicker::make('assembly_date')
                            ->label('Tanggal Assembly')
                            ->default(now())
                            ->required(),

                        Forms\Components\Section::make('Pilih Serial Number Komponen')
                            ->description('Slot mengikuti BOM tipe perangkat yang didefinisikan admin')
                            ->schema(function (Forms\Get $get) {
                                $type = $get('product_type');
                                if (! $type) {
                                    return [];
                                }

                                return \App\Services\DeviceTypeCatalog::assemblySnDetailFields((string) $type);
                            })->columns(2)
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        AssemblyInvoiceValidator::ensureCanCreateAssembly($data['invoice_id']);

                        $data['serial_number'] = AssemblySerialNumberGenerator::nextForDate(
                            $data['assembly_date'] ?? now()
                        );

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

