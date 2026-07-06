<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Attendance;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendance extends ViewRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Terima')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Attendance $record) => $record->status === 'pending')
                ->requiresConfirmation()
                ->action(function (Attendance $record) {
                    $record->update([
                        'status' => 'approved',
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                        'admin_notes' => null,
                    ]);
                })
                ->successNotificationTitle('Absensi diterima'),
            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Attendance $record) => $record->status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Alasan penolakan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (Attendance $record, array $data) {
                    $record->update([
                        'status' => 'rejected',
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                        'admin_notes' => $data['admin_notes'],
                    ]);
                })
                ->successNotificationTitle('Absensi ditolak'),
        ];
    }
}
