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
        return (string) config('app.timezone', 'Asia/Jakarta');
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

        $time = $attendance->recorded_at->format('H:i');

        if ($attendance->hasPendingChangeRequest()) {
            return $time.' (koreksi menunggu)';
        }

        $statusSuffix = match ($attendance->status) {
            'pending' => ' (menunggu)',
            'rejected' => ' (ditolak)',
            default => '',
        };

        return $time.$statusSuffix;
    }

    /**
     * Hari hadir = jumlah tanggal unik dengan tap-in berstatus approved di bulan tersebut.
     */
    public function countPresentDays(Employee $employee, int $month, int $year): int
    {
        $tz = $this->timezone();
        $start = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $dates = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('type', AttendanceType::TapIn)
            ->where('status', 'approved')
            ->whereBetween('recorded_at', [$start, $end])
            ->pluck('recorded_at');

        return $dates
            ->map(fn ($at) => Carbon::parse($at)->timezone($tz)->toDateString())
            ->unique()
            ->count();
    }

    /**
     * @return array{
     *     min_days: int,
     *     days_present: int,
     *     missing_days: int,
     *     daily_rate: float,
     *     deduction: float
     * }
     */
    public function attendanceDeductionFor(Employee $employee, int $month, int $year): array
    {
        $minDays = max(1, (int) config('attendance.min_present_days', 24));
        $daysPresent = $this->countPresentDays($employee, $month, $year);
        $missingDays = max(0, $minDays - $daysPresent);
        $baseSalary = (float) $employee->base_salary;
        $dailyRate = $baseSalary / $minDays;
        $deduction = round($dailyRate * $missingDays, 2);

        return [
            'min_days' => $minDays,
            'days_present' => $daysPresent,
            'missing_days' => $missingDays,
            'daily_rate' => round($dailyRate, 2),
            'deduction' => $deduction,
        ];
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
