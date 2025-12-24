<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class SalarySlipController extends Controller
{
    public function show(Employee $employee, Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Hitung total cashbon yang sudah approved di bulan tersebut
        $totalCashbon = $employee->cashbons()
            ->where('status', 'approved')
            ->whereMonth('request_date', $month)
            ->whereYear('request_date', $year)
            ->sum('amount');

        // Hitung gaji bersih
        $gajiBersih = $employee->base_salary - $totalCashbon - $employee->bpjs_allowance;

        $data = [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'base_salary' => $employee->base_salary,
            'total_cashbon' => $totalCashbon,
            'bpjs_allowance' => $employee->bpjs_allowance,
            'gaji_bersih' => $gajiBersih,
            'cashbons' => $employee->cashbons()
                ->where('status', 'approved')
                ->whereMonth('request_date', $month)
                ->whereYear('request_date', $year)
                ->get(),
        ];

        if ($request->get('pdf')) {
            $pdf = Pdf::loadView('salary-slips.show', $data);
            return $pdf->download("slip-gaji-{$employee->name}-{$year}-{$month}.pdf");
        }

        return view('salary-slips.show', $data);
    }
}

