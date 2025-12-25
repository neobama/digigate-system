<?php

namespace App\Filament\Pages;

use App\Exports\BackupAllDataExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class BackupData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Backup Data';
    protected static ?string $title = 'Backup All Data';
    protected static ?string $navigationGroup = 'Settings';
    protected static string $view = 'filament.pages.backup-data';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadBackup')
                ->label('Download Backup Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Download Backup Data')
                ->modalDescription('File Excel akan berisi semua data dari sistem (Invoice, Assembly, Employee, Logbook, Cashbon, Component). Foto tidak termasuk dalam backup.')
                ->modalSubmitActionLabel('Download')
                ->action(function () {
                    $filename = 'backup-data-' . date('Y-m-d-His') . '.xlsx';
                    
                    return Excel::download(new BackupAllDataExport(), $filename);
                }),
        ];
    }
}
