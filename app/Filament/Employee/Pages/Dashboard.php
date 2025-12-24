<?php

namespace App\Filament\Employee\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            \App\Filament\Employee\Widgets\StockSummaryWidget::class,
            \App\Filament\Employee\Widgets\UnassembledInvoicesWidget::class,
        ];
    }
}

