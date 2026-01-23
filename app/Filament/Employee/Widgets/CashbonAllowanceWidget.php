<?php

namespace App\Filament\Employee\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CashbonAllowanceWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $employee = Auth::user()->employee;
        
        if (!$employee) {
            return [
                Stat::make('Jatah Cashbon', 'Data tidak tersedia')
                    ->description('Data employee tidak ditemukan')
                    ->color('gray'),
            ];
        }

        $maxAllowance = $employee->getMaxCashbonPerMonth();
        $used = $employee->getCurrentMonthCashbonTotal();
        $remaining = $employee->getRemainingCashbonAllowance();
        $percentage = $maxAllowance > 0 ? round(($used / $maxAllowance) * 100, 1) : 0;

        return [
            Stat::make('Jatah Cashbon Bulanan', 'Rp ' . number_format($maxAllowance, 0, ',', '.'))
                ->description('35% dari gaji bulanan')
                ->color('info'),
            
            Stat::make('Sisa Jatah Cashbon', 'Rp ' . number_format($remaining, 0, ',', '.'))
                ->description('Sisa jatah yang dapat digunakan')
                ->color($remaining > 0 ? 'success' : 'danger'),
            
            Stat::make('Terpakai', 'Rp ' . number_format($used, 0, ',', '.') . ' (' . $percentage . '%)')
                ->description('Total cashbon bulan ini')
                ->color($percentage < 80 ? 'success' : ($percentage < 100 ? 'warning' : 'danger')),
        ];
    }
}
