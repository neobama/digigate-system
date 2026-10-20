<?php

namespace App\Services;

use App\Filament\Resources\SalaryPaymentResource;
use App\Models\Employee;
use App\Models\SalaryPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalarySlipService
{
    public function buildData(Employee $employee, int $month, int $year, ?SalaryPayment $salaryPayment = null): array
    {
        $cashbonDetails = SalaryPaymentResource::getCashbonDetailsForPeriod($employee, $month, $year);
        $totalCashbon = SalaryPaymentResource::calculateMonthlyCashbon($employee, $month, $year);

        $attendance = SalaryPaymentResource::calculateAttendanceDeduction($employee, $month, $year);
        $applyAttendance = $salaryPayment
            ? (bool) $salaryPayment->apply_attendance_deduction
            : true;
        $attendanceDeduction = $applyAttendance ? (float) $attendance['deduction'] : 0.0;
        $daysPresent = (int) $attendance['days_present'];

        $gajiBersih = (float) $employee->base_salary - $totalCashbon - (float) $employee->bpjs_allowance - $attendanceDeduction;
        $adjustmentItems = [];
        $adjustmentAddition = 0;
        $adjustmentDeduction = 0;

        if ($salaryPayment) {
            $salaryPayment->loadMissing('adjustments');
            $adjustmentItems = $salaryPayment->adjustments->toArray();
            $adjustmentAddition = (float) $salaryPayment->adjustment_addition;
            $adjustmentDeduction = (float) $salaryPayment->adjustment_deduction;
            $attendanceDeduction = (float) $salaryPayment->attendance_deduction;
            $daysPresent = (int) $salaryPayment->days_present;
            $applyAttendance = (bool) $salaryPayment->apply_attendance_deduction;
            $gajiBersih = (float) $salaryPayment->net_salary;
        }

        return [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'logo_src' => $this->resolveLogoSrc(),
            'base_salary' => $employee->base_salary,
            'total_cashbon' => $totalCashbon,
            'bpjs_allowance' => $employee->bpjs_allowance,
            'days_present' => $daysPresent,
            'min_present_days' => $attendance['min_days'],
            'apply_attendance_deduction' => $applyAttendance,
            'attendance_deduction' => $attendanceDeduction,
            'gaji_bersih' => $gajiBersih,
            'cashbon_details' => $cashbonDetails,
            'adjustment_items' => $adjustmentItems,
            'adjustment_addition' => $adjustmentAddition,
            'adjustment_deduction' => $adjustmentDeduction,
        ];
    }

    public function generatePdfBinary(SalaryPayment $salaryPayment): string
    {
        $salaryPayment->loadMissing('employee', 'adjustments');
        $employee = $salaryPayment->employee;

        if (! $employee) {
            throw new \RuntimeException('Salary payment tanpa employee.');
        }

        $data = $this->buildData(
            $employee,
            (int) $salaryPayment->month,
            (int) $salaryPayment->year,
            $salaryPayment
        );

        return Pdf::loadView('salary-slips.show', $data)->output();
    }

    public function filename(SalaryPayment $salaryPayment): string
    {
        $salaryPayment->loadMissing('employee');
        $name = Str::slug($salaryPayment->employee?->name ?? 'karyawan', '-');

        return sprintf(
            'slip-gaji-%s-%s-%02d.pdf',
            $name,
            $salaryPayment->year,
            $salaryPayment->month
        );
    }

    private function resolveLogoSrc(): string
    {
        $logoUrl = 'https://is3.cloudhost.id/s3-digigate/assets/digigate-logo.png';

        try {
            $response = Http::timeout(10)->get($logoUrl);
            if ($response->successful()) {
                $mimeType = $response->header('Content-Type', 'image/png');

                return 'data:'.$mimeType.';base64,'.base64_encode($response->body());
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to fetch salary slip logo from S3 URL', [
                'url' => $logoUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return $logoUrl;
    }
}
