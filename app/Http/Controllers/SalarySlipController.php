<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SalaryPaymentResource;
use App\Models\Employee;
use App\Models\SalaryPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalarySlipController extends Controller
{
    public function showBySalaryPayment(SalaryPayment $salaryPayment, Request $request)
    {
        $salaryPayment->loadMissing('employee', 'adjustments');

        $employee = $salaryPayment->employee;
        abort_unless($employee, 404);

        $month = (int) $salaryPayment->month;
        $year = (int) $salaryPayment->year;

        $data = $this->buildSlipData($employee, $month, $year, $salaryPayment);

        if ($request->get('pdf')) {
            $pdf = Pdf::loadView('salary-slips.show', $data);

            return $pdf->download("slip-gaji-{$employee->name}-{$year}-{$month}.pdf");
        }

        return view('salary-slips.show', $data);
    }

    public function show(Employee $employee, Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $salaryPayment = SalaryPayment::with('adjustments')
            ->where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $data = $this->buildSlipData($employee, (int) $month, (int) $year, $salaryPayment);

        if ($request->get('pdf')) {
            $pdf = Pdf::loadView('salary-slips.show', $data);

            return $pdf->download("slip-gaji-{$employee->name}-{$year}-{$month}.pdf");
        }

        return view('salary-slips.show', $data);
    }

    private function buildSlipData(Employee $employee, int $month, int $year, ?SalaryPayment $salaryPayment = null): array
    {
        $cashbonDetails = SalaryPaymentResource::getCashbonDetailsForPeriod($employee, $month, $year);
        $totalCashbon = SalaryPaymentResource::calculateMonthlyCashbon($employee, $month, $year);

        // Hitung gaji bersih
        $gajiBersih = $employee->base_salary - $totalCashbon - $employee->bpjs_allowance;
        $adjustmentItems = [];
        $adjustmentAddition = 0;
        $adjustmentDeduction = 0;

        if ($salaryPayment) {
            $adjustmentItems = $salaryPayment->adjustments->toArray();
            $adjustmentAddition = (float) $salaryPayment->adjustment_addition;
            $adjustmentDeduction = (float) $salaryPayment->adjustment_deduction;
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
            'gaji_bersih' => $gajiBersih,
            'cashbon_details' => $cashbonDetails,
            'adjustment_items' => $adjustmentItems,
            'adjustment_addition' => $adjustmentAddition,
            'adjustment_deduction' => $adjustmentDeduction,
        ];
    }

    private function resolveLogoSrc(): string
    {
        $logoUrl = 'https://is3.cloudhost.id/s3-digigate/assets/digigate-logo.png';

        // DomPDF is more reliable with data-uri for external images.
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
