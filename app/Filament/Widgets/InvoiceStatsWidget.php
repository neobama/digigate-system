<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pendapatan Bulan Ini', function () {
                // Cash basis: jumlah pembayaran yang masuk bulan ini (termasuk DP/cicilan)
                $total = InvoicePayment::query()
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount');

                return 'Rp '.number_format($total, 0, ',', '.');
            })
                ->description('Total pembayaran masuk (termasuk partial)')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pendapatan Bulan Lalu', function () {
                $lastMonth = now()->subMonth();
                $total = InvoicePayment::query()
                    ->whereMonth('paid_at', $lastMonth->month)
                    ->whereYear('paid_at', $lastMonth->year)
                    ->sum('amount');

                return 'Rp '.number_format($total, 0, ',', '.');
            })
                ->description('Bulan '.now()->subMonth()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Menunggu Pelunasan', function () {
                return Invoice::query()
                    ->whereIn('status', ['proforma', 'partially_paid'])
                    ->count();
            })
                ->description('Proforma + partial')
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
