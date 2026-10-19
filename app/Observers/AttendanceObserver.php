<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Services\ActivityLogService;

class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        ActivityLogService::logCreate($attendance);
    }

    public function updated(Attendance $attendance): void
    {
        $oldValues = $attendance->getOriginal();
        $newValues = $attendance->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($attendance, $oldValues, $newValues);
        }
    }

    public function deleted(Attendance $attendance): void
    {
        ActivityLogService::logDelete($attendance);
    }
}
