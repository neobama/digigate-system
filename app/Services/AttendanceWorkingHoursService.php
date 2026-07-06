<?php

namespace App\Services;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceWorkingHoursService
{
    public function timezone(): string
    {
        return 'Asia/Jakarta';
    }

    /**
     * @param  array<int, string>  $statuses
     */
    public function findForDay(
        Employee $employee,
        Carbon $date,
        AttendanceType $type,
        array $statuses = ['pending', 'approved']
    ): ?Attendance {
        [$start, $end] = $this->dayRange($date);

        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->whereBetween('recorded_at', [$start, $end])
            ->whereIn('status', $statuses)
            ->orderBy('recorded_at')
            ->first();
    }

    public function hasSubmittedToday(Employee $employee, AttendanceType $type): bool
    {
        return $this->findForDay($employee, now($this->timezone()), $type) !== null;
    }

    public function canTapOutToday(Employee $employee): bool
    {
        return $this->findForDay($employee, now($this->timezone()), AttendanceType::TapIn) !== null;
    }

    public function resolveNextType(Employee $employee): ?AttendanceType
    {
        $today = now($this->timezone());

        if (! $this->findForDay($employee, $today, AttendanceType::TapIn)) {
            return AttendanceType::TapIn;
        }

        if (! $this->findForDay($employee, $today, AttendanceType::TapOut)) {
            return AttendanceType::TapOut;
        }

        return null;
    }

    public function calculateMinutes(?Attendance $tapIn, ?Attendance $tapOut, bool $approvedOnly = true): ?int
    {
        if (! $tapIn || ! $tapOut) {
            return null;
        }

        if ($approvedOnly && ($tapIn->status !== 'approved' || $tapOut->status !== 'approved')) {
            return null;
        }

        if ($tapOut->recorded_at->lessThanOrEqualTo($tapIn->recorded_at)) {
            return null;
        }

        return (int) $tapIn->recorded_at->diffInMinutes($tapOut->recorded_at);
    }

    public function dailySummary(Employee $employee, Carbon $date): array
    {
        $tapIn = $this->findForDay($employee, $date, AttendanceType::TapIn);
        $tapOut = $this->findForDay($employee, $date, AttendanceType::TapOut);
        $minutes = $this->calculateMinutes($tapIn, $tapOut);

        return [
            'employee' => $employee,
            'date' => $date->copy()->startOfDay(),
            'tap_in' => $tapIn,
            'tap_out' => $tapOut,
            'working_minutes' => $minutes,
            'working_hours_label' => self::formatMinutes($minutes),
            'is_complete' => $tapIn !== null && $tapOut !== null,
            'is_verified' => $minutes !== null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summariesForDate(Carbon $date, ?string $employeeId = null): Collection
    {
        $query = Employee::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($employeeId) {
            $query->where('id', $employeeId);
        }

        return $query
            ->get()
            ->map(fn (Employee $employee) => $this->dailySummary($employee, $date));
    }

    public static function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} jam {$remainingMinutes} menit";
        }

        if ($hours > 0) {
            return "{$hours} jam";
        }

        return "{$remainingMinutes} menit";
    }

    public static function formatAttendanceTime(?Attendance $attendance): string
    {
        if (! $attendance) {
            return '-';
        }

        $time = $attendance->recorded_at
            ->timezone('Asia/Jakarta')
            ->format('H:i');

        $statusSuffix = match ($attendance->status) {
            'pending' => ' (menunggu)',
            'rejected' => ' (ditolak)',
            default => '',
        };

        return $time.$statusSuffix;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayRange(Carbon $date): array
    {
        $localized = $date->copy()->timezone($this->timezone());

        return [
            $localized->copy()->startOfDay(),
            $localized->copy()->endOfDay(),
        ];
    }
}
