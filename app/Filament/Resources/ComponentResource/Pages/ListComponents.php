<?php

namespace App\Filament\Resources\ComponentResource\Pages;

use App\Filament\Resources\ComponentResource;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListComponents extends ListRecords
{
    protected static string $resource = ComponentResource::class;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (): ?string => null;
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        // Selalu prioritaskan stok tersedia di atas.
        $query->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END");

        if (filled($this->getTableSortColumn())) {
            return parent::applySortingToTableQuery($query);
        }

        return $query
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
