<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Income;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $now = now();
        $month = $now->month;
        $year = $now->year;

        // Pemasukan: single source = incomes
        // (invoice paid/delivered auto-post + manual incomes)
        $invoiceIncome = Income::query()
            ->whereNotNull('invoice_id')
            ->whereMonth('income_date', $month)
            ->whereYear('income_date', $year)
            ->sum('amount');

        $manualIncome = Income::query()
            ->whereNull('invoice_id')
            ->whereMonth('income_date', $month)
            ->whereYear('income_date', $year)
            ->sum('amount');

        $totalIncome = $invoiceIncome + $manualIncome;

        // Pengeluaran: single source = expenses
        $totalExpense = Expense::whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->sum('amount');

        $profit = $totalIncome - $totalExpense;

        return [
            Stat::make('Total Pemasukan Bulan Ini', 'Rp '.number_format($totalIncome, 0, ',', '.'))
                ->description('Invoice: '.number_format($invoiceIncome, 0, ',', '.').' | Manual: '.number_format($manualIncome, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Pengeluaran Bulan Ini', 'Rp '.number_format($totalExpense, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Laba/Rugi Bulan Ini', 'Rp '.number_format($profit, 0, ',', '.'))
                ->description($profit >= 0 ? 'Laba' : 'Rugi')
                ->descriptionIcon($profit >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($profit >= 0 ? 'success' : 'danger'),
        ];
    }
}
