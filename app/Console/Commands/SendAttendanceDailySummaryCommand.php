<?php

namespace App\Console\Commands;

use App\Services\AttendanceWorkingHoursService;
use App\Services\TelegramNotifier;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAttendanceDailySummaryCommand extends Command
{
    protected $signature = 'attendance:daily-summary {--date= : Tanggal Y-m-d, default hari ini}';

    protected $description = 'Kirim ringkasan absensi harian ke admin via Telegram';

    public function handle(
        TelegramService $telegram,
        TelegramNotifier $notifier,
        AttendanceWorkingHoursService $attendance,
    ): int {
        if (! $telegram->enabled()) {
            $this->warn('Telegram belum dikonfigurasi.');

            return self::SUCCESS;
        }

        $tz = $attendance->timezone();
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'), $tz)
            : now($tz);

        $notifier->sendDailyAttendanceSummary($date);
        $this->info('Ringkasan absensi '.$date->toDateString().' dikirim ke admin.');

        return self::SUCCESS;
    }
}
