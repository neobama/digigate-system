<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Services\SalarySlipService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SalarySlipController extends Controller
{
    public function __construct(private SalarySlipService $salarySlips) {}

    public function showBySalaryPayment(SalaryPayment $salaryPayment, Request $request)
    {
        $salaryPayment->loadMissing('employee', 'adjustments');

        $employee = $salaryPayment->employee;
        abort_unless($employee, 404);

        $month = (int) $salaryPayment->month;
        $year = (int) $salaryPayment->year;

        $data = $this->salarySlips->buildData($employee, $month, $year, $salaryPayment);

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

        $data = $this->salarySlips->buildData($employee, (int) $month, (int) $year, $salaryPayment);

        if ($request->get('pdf')) {
            $pdf = Pdf::loadView('salary-slips.show', $data);

            return $pdf->download("slip-gaji-{$employee->name}-{$year}-{$month}.pdf");
        }

        return view('salary-slips.show', $data);
    }
}
