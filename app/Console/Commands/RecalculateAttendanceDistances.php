<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\AttendanceLocationService;
use Illuminate\Console\Command;

class RecalculateAttendanceDistances extends Command
{
    protected $signature = 'attendance:recalculate-distances';

    protected $description = 'Hitung ulang jarak absensi dari koordinat kantor yang benar';

    public function handle(AttendanceLocationService $locationService): int
    {
        $updated = 0;

        Attendance::query()
            ->select(['id', 'latitude', 'longitude', 'distance_meters', 'is_within_radius'])
            ->chunkById(100, function ($attendances) use ($locationService, &$updated) {
                foreach ($attendances as $attendance) {
                    $evaluation = $locationService->evaluate(
                        (float) $attendance->latitude,
                        (float) $attendance->longitude
                    );

                    if (
                        (float) $attendance->distance_meters === (float) $evaluation['distance_meters']
                        && (bool) $attendance->is_within_radius === $evaluation['is_within_radius']
                    ) {
                        continue;
                    }

                    $attendance->update([
                        'distance_meters' => $evaluation['distance_meters'],
                        'is_within_radius' => $evaluation['is_within_radius'],
                    ]);

                    $updated++;
                }
            });

        $this->info("Koordinat kantor: {$locationService->officeLatitude()}, {$locationService->officeLongitude()}");
        $this->info("Radius: {$locationService->radiusMeters()} m");
        $this->info("Absensi diperbarui: {$updated}");

        return self::SUCCESS;
    }
}
