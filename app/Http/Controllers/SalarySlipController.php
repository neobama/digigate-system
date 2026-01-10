<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Cashbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class SalarySlipController extends Controller
{
    public function show(Employee $employee, Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $currentDate = \Carbon\Carbon::create($year, $month, 1);

        // Hitung total cashbon yang harus dipotong di bulan tersebut
        $totalCashbon = 0;
        $cashbonDetails = [];

        // Ambil semua cashbon yang sudah paid untuk employee ini
        // Query langsung dari model Cashbon untuk memastikan hanya mengambil yang benar-benar ada
        $cashbons = Cashbon::where('employee_id', $employee->id)
            ->where('status', 'paid')
            ->get();

        foreach ($cashbons as $cashbon) {
            // Pastikan cashbon masih ada dan memiliki relasi employee yang valid
            if (!$cashbon || !$cashbon->employee) {
                continue;
            }
            $requestDate = \Carbon\Carbon::parse($cashbon->request_date);
            $installmentMonths = $cashbon->installment_months;

            if ($installmentMonths === null) {
                // Langsung dipotong di bulan request_date
                if ($requestDate->month == $month && $requestDate->year == $year) {
                    $totalCashbon += $cashbon->amount;
                    $cashbonDetails[] = [
                        'cashbon' => $cashbon,
                        'amount' => $cashbon->amount,
                        'type' => 'langsung',
                    ];
                }
            } else {
                // Dicicil selama N bulan
                $startDate = \Carbon\Carbon::create($requestDate->year, $requestDate->month, 1);
                $endDate = $startDate->copy()->addMonths($installmentMonths - 1)->endOfMonth();
                
                // Cek apakah bulan yang sedang dihitung termasuk dalam periode cicilan
                // Gunakan >= dan <= untuk memastikan bulan pertama dan terakhir termasuk
                if ($currentDate->year == $startDate->year && $currentDate->month == $startDate->month) {
                    // Bulan pertama cicilan
                    $monthlyInstallment = $cashbon->amount / $installmentMonths;
                    $totalCashbon += $monthlyInstallment;
                    
                    $cashbonDetails[] = [
                        'cashbon' => $cashbon,
                        'amount' => $monthlyInstallment,
                        'type' => 'cicilan',
                        'installment_number' => 1,
                        'total_installments' => $installmentMonths,
                    ];
                } elseif ($currentDate->greaterThan($startDate) && $currentDate->lessThanOrEqualTo($endDate)) {
                    // Bulan tengah atau akhir cicilan
                    $monthsDiff = $currentDate->diffInMonths($startDate);
                    if ($monthsDiff < $installmentMonths) {
                        $monthlyInstallment = $cashbon->amount / $installmentMonths;
                        $totalCashbon += $monthlyInstallment;
                        
                        $cashbonDetails[] = [
                            'cashbon' => $cashbon,
                            'amount' => $monthlyInstallment,
                            'type' => 'cicilan',
                            'installment_number' => $monthsDiff + 1,
                            'total_installments' => $installmentMonths,
                        ];
                    }
                }
            }
        }

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
            'cashbon_details' => $cashbonDetails,
        ];

        if ($request->get('pdf')) {
            $pdf = Pdf::loadView('salary-slips.show', $data);
            return $pdf->download("slip-gaji-{$employee->name}-{$year}-{$month}.pdf");
        }

        return view('salary-slips.show', $data);
    }
}

