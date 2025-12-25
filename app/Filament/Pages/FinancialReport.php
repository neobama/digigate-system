<?php

namespace App\Filament\Pages;

use App\Exports\FinancialReportExport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class FinancialReport extends Page
{
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Select::make('month')
                        ->label('Bulan')
                        ->options([
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                            4 => 'April', 5 => 'Mei', 6 => 'Juni',
                            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
                            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ])
                        ->default($this->month)
                        ->required(),
                    Select::make('year')
                        ->label('Tahun')
                        ->options(function () {
                            $years = [];
                            for ($i = now()->year; $i >= now()->year - 5; $i--) {
                                $years[$i] = $i;
                            }
                            return $years;
                        })
                        ->default($this->year)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filename = 'Laporan_Keuangan_' . \Carbon\Carbon::create($data['year'], $data['month'], 1)->format('F_Y') . '.xlsx';
                    
                    Notification::make()
                        ->success()
                        ->title('Export Berhasil')
                        ->body('Laporan keuangan sedang didownload...')
                        ->send();
                    
                    return Excel::download(
                        new FinancialReportExport($data['month'], $data['year']),
                        $filename
                    );
                }),
        ];
    }
}
