<?php

namespace App\Filament\Employee\Widgets;

use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UnassembledInvoicesWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Invoice Paid yang Belum Dirakit')
            ->description('Daftar invoice yang sudah dibayar dan siap untuk dirakit')
            ->query(
                Invoice::query()
                    ->where('status', 'paid')
                    ->whereDoesntHave('assemblies')
                    ->orderBy('invoice_date', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'paid',
                    ]),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->paginated(false);
    }
}

