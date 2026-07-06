<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Services\ActivityLogService;
use App\Services\WhatsAppService;

class AttendanceObserver
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function created(Attendance $attendance): void
    {
        ActivityLogService::logCreate($attendance);

        $attendance->load('employee');
        $employee = $attendance->employee;

        if (! $employee) {
            return;
        }

        $recordedAt = $attendance->recorded_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-';
        $locationNote = $attendance->is_within_radius
            ? 'Dalam radius kantor'
            : 'Di luar radius ('.number_format((float) $attendance->distance_meters, 0, ',', '.').' m)';

        $message = "📸 *Absensi Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Waktu: {$recordedAt}\n";
        $message .= "Lokasi: {$locationNote}\n";
        $message .= "Koordinat: {$attendance->latitude}, {$attendance->longitude}\n";
        $message .= 'Status: Menunggu verifikasi';

        if ($attendance->description) {
            $message .= "\nKeterangan: {$attendance->description}";
        }

        try {
            $this->whatsapp->sendToAdmin($message);
        } catch (\Exception $e) {
            \Log::error('Failed to send WhatsApp notification for new attendance', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Attendance $attendance): void
    {
        $oldValues = $attendance->getOriginal();
        $newValues = $attendance->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($attendance, $oldValues, $newValues);
        }

        if (! $attendance->wasChanged('status')) {
            return;
        }

        $attendance->load('employee');
        $employee = $attendance->employee;

        if (! $employee || empty($employee->phone_number)) {
            return;
        }

        if (! in_array($attendance->status, ['approved', 'rejected'], true)) {
            return;
        }

        $recordedAt = $attendance->recorded_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-';

        $employeeMessage = "📸 *Update Absensi Anda*\n\n";
        $employeeMessage .= "Waktu absen: {$recordedAt}\n";

        if ($attendance->status === 'approved') {
            $employeeMessage .= "✅ Status: *Diterima*\n\n";
            $employeeMessage .= 'Absensi Anda telah diverifikasi admin.';
        } else {
            $employeeMessage .= "❌ Status: *Ditolak*\n\n";
            if ($attendance->admin_notes) {
                $employeeMessage .= "Alasan: {$attendance->admin_notes}";
            }
        }

        try {
            $this->whatsapp->sendMessage($employee->phone_number, $employeeMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to send WhatsApp notification to employee for attendance', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(Attendance $attendance): void
    {
        ActivityLogService::logDelete($attendance);
    }
}
