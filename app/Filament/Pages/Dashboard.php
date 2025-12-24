<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\InvoiceStatsWidget::class,
            \App\Filament\Widgets\StockSummaryWidget::class,
        ];
    }
}

