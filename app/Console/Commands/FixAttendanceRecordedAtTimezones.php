<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixAttendanceRecordedAtTimezones extends Command
{
    protected $signature = 'attendance:fix-timezones';

    protected $description = 'Perbaiki recorded_at absensi lama yang tersimpan sebagai jam WIB di kolom UTC';

    public function handle(): int
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $updated = 0;

        Attendance::query()
            ->select(['id', 'recorded_at'])
            ->chunkById(100, function ($attendances) use ($timezone, &$updated) {
                foreach ($attendances as $attendance) {
                    $raw = $attendance->getRawOriginal('recorded_at');

                    if (! $raw) {
                        continue;
                    }

                    $attendance->recorded_at = Carbon::parse($raw, $timezone);
                    $attendance->saveQuietly();
                    $updated++;
                }
            });

        $this->info("Timezone aplikasi: {$timezone}");
        $this->info("Absensi diperbarui: {$updated}");

        return self::SUCCESS;
    }
}
