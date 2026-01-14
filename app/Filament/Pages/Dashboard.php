<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Dashboard';
    
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\InvoiceStatsWidget::class,
            \App\Filament\Widgets\FinancialOverviewWidget::class,
            \App\Filament\Widgets\TaskCalendarWidget::class,
            \App\Filament\Widgets\StockSummaryWidget::class,
        ];
    }
}

