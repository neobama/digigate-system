<?php

namespace App\Filament\Widgets;

use App\Models\Cashbon;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Reimbursement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $now = now();
        $month = $now->month;
        $year = $now->year;

        // Pemasukan dari Invoice Paid dan Delivered
        // Include both 'paid' and 'delivered' status (delivered means already paid)
        // Use paid_date instead of invoice_date for revenue recognition
        $invoiceIncome = Invoice::whereIn('status', ['paid', 'delivered'])
            ->whereNotNull('paid_date')
            ->whereMonth('paid_date', $month)
            ->whereYear('paid_date', $year)
            ->sum('total_amount');

        // Pemasukan Manual
        $manualIncome = Income::whereMonth('income_date', $month)
            ->whereYear('income_date', $year)
            ->sum('amount');

        // Total Pemasukan
        $totalIncome = $invoiceIncome + $manualIncome;

        // Pengeluaran dari Reimbursement Paid
        // Only count reimbursements with valid employee relationship
        $reimbursementExpense = Reimbursement::where('status', 'paid')
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->whereHas('employee') // Only include reimbursements with valid employee
            ->sum('amount');

        // Pengeluaran dari Cashbon Paid
        // Only count cashbons with valid employee relationship
        $cashbonExpense = Cashbon::where('status', 'paid')
            ->whereMonth('request_date', $month)
            ->whereYear('request_date', $year)
            ->whereHas('employee') // Only include cashbons with valid employee
            ->sum('amount');

        // Pengeluaran Manual
        $manualExpense = Expense::whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->sum('amount');

        // Total Pengeluaran
        $totalExpense = $reimbursementExpense + $cashbonExpense + $manualExpense;

        // Laba/Rugi
        $profit = $totalIncome - $totalExpense;

        return [
            Stat::make('Total Pemasukan Bulan Ini', 'Rp ' . number_format($totalIncome, 0, ',', '.'))
                ->description('Invoice: ' . number_format($invoiceIncome, 0, ',', '.') . ' | Manual: ' . number_format($manualIncome, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('Total Pengeluaran Bulan Ini', 'Rp ' . number_format($totalExpense, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            
            Stat::make('Laba/Rugi Bulan Ini', 'Rp ' . number_format($profit, 0, ',', '.'))
                ->description($profit >= 0 ? 'Laba' : 'Rugi')
                ->descriptionIcon($profit >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($profit >= 0 ? 'success' : 'danger'),
        ];
    }
}

