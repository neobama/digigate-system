<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pendapatan Bulan Ini', function () {
                $total = Invoice::where('status', 'paid')
                    ->whereMonth('invoice_date', now()->month)
                    ->whereYear('invoice_date', now()->year)
                    ->sum('total_amount');
                
                return 'Rp ' . number_format($total, 0, ',', '.');
            })
                ->description('Total invoice yang sudah dibayar')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('Pendapatan Bulan Lalu', function () {
                $lastMonth = now()->subMonth();
                $total = Invoice::where('status', 'paid')
                    ->whereMonth('invoice_date', $lastMonth->month)
                    ->whereYear('invoice_date', $lastMonth->year)
                    ->sum('total_amount');
                
                return 'Rp ' . number_format($total, 0, ',', '.');
            })
                ->description('Bulan ' . now()->subMonth()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
            
            Stat::make('Invoice Proforma', function () {
                return Invoice::where('status', 'proforma')->count();
            })
                ->description('Menunggu pembayaran')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Total Invoice Bulan Ini', function () {
                return Invoice::whereMonth('invoice_date', now()->month)
                    ->whereYear('invoice_date', now()->year)
                    ->count();
            })
                ->description('Semua status')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
        ];
    }
}

