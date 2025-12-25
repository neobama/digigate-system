<?php

namespace App\Filament\Pages;

use App\Exports\FinancialReportExport;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class FinancialReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.financial-report';
    protected static ?string $navigationLabel = 'Laporan Keuangan';
    protected static ?string $navigationGroup = 'Financial';
    protected static ?string $title = 'Laporan Keuangan';

    public $month;
    public $year;

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('month')
                ->label('Bulan')
                ->options([
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                    4 => 'April', 5 => 'Mei', 6 => 'Juni',
                    7 => 'Juli', 8 => 'Agustus', 9 => 'September',
                    10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ])
                ->default(now()->month)
                ->required()
                ->reactive(),
            Select::make('year')
                ->label('Tahun')
                ->options(function () {
                    $years = [];
                    for ($i = now()->year; $i >= now()->year - 5; $i--) {
                        $years[$i] = $i;
                    }
                    return $years;
                })
                ->default(now()->year)
                ->required()
                ->reactive(),
        ];
    }

    public function export()
    {
        $this->validate();
        
        $filename = 'Laporan_Keuangan_' . \Carbon\Carbon::create($this->year, $this->month, 1)->format('F_Y') . '.xlsx';
        
        Notification::make()
            ->success()
            ->title('Export Berhasil')
            ->body('Laporan keuangan sedang didownload...')
            ->send();
        
        return Excel::download(
            new FinancialReportExport($this->month, $this->year),
            $filename
        );
    }
}
