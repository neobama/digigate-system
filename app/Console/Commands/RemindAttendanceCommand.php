<?php

namespace App\Console\Commands;

use App\Enums\AttendanceType;
use App\Models\Employee;
use App\Services\AttendanceWorkingHoursService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class RemindAttendanceCommand extends Command
{
    protected $signature = 'attendance:remind {type : tap_in atau tap_out}';

    protected $description = 'Kirim pengingat Telegram absensi ke karyawan yang belum tap in/out (hari kerja)';

    public function handle(AttendanceWorkingHoursService $attendance, TelegramService $telegram): int
    {
        if (! $telegram->enabled()) {
            $this->warn('Telegram belum dikonfigurasi.');

            return self::SUCCESS;
        }

        $type = match ($this->argument('type')) {
            'tap_in', 'in' => AttendanceType::TapIn,
            'tap_out', 'out' => AttendanceType::TapOut,
            default => null,
        };

        if (! $type) {
            $this->error('Type harus tap_in atau tap_out.');

            return self::FAILURE;
        }

        $today = now($attendance->timezone());
        if ($today->isWeekend()) {
            $this->info('Hari libur — skip pengingat.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        Employee::query()
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->orderBy('name')
            ->each(function (Employee $employee) use ($attendance, $telegram, $type, &$sent, &$skipped) {
                if ($attendance->hasSubmittedToday($employee, $type)) {
                    $skipped++;

                    return;
                }

                // Tap out hanya diingatkan jika sudah tap in.
                if ($type === AttendanceType::TapOut && ! $attendance->hasSubmittedToday($employee, AttendanceType::TapIn)) {
                    $skipped++;

                    return;
                }

                $message = $type === AttendanceType::TapIn
                    ? "⏰ <b>Pengingat Absensi</b>\n\nHalo {$this->e($employee->name)}, Anda belum <b>Tap In</b> hari ini.\nSilakan absen masuk di panel karyawan DigiGate."
                    : "⏰ <b>Pengingat Absensi</b>\n\nHalo {$this->e($employee->name)}, Anda belum <b>Tap Out</b> hari ini.\nJangan lupa absen pulang di panel karyawan DigiGate.";

                if ($telegram->sendToEmployee($employee, $message, [], defer: false)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            });

        $this->info("Pengingat {$type->value}: terkirim={$sent}, dilewati={$skipped}");

        return self::SUCCESS;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
