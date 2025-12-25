<?php

namespace App\Filament\Employee\Widgets;

use App\Models\Component;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class StockSummaryWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ringkasan Stock Komponen')
            ->description('Breakdown stock per komponen yang tersedia')
            ->query(
                Component::query()
                    ->selectRaw('MIN(id) as id, name, 
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available', 
                        ['available'])
                    ->groupBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Komponen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('available')
                    ->label('Tersedia (pcs)')
                    ->sortable()
                    ->alignCenter()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => $state . ' pcs'),
            ])
            ->defaultSort('name', 'asc')
            ->paginated(false);
    }
}

