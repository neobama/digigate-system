<?php

namespace App\Filament\Resources\SalaryPaymentResource\Pages;

use App\Filament\Resources\SalaryPaymentResource;
use App\Models\Cashbon;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;

class EditSalaryPayment extends EditRecord
{
    protected static string $resource = SalaryPaymentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $month = (int) $data['month'];
        $year = (int) $data['year'];

        $totalCashbon = $this->calculateMonthlyCashbon($employee, $month, $year);
        $baseSalary = (float) $employee->base_salary;
        $bpjs = (float) $employee->bpjs_allowance;
        $addition = (float) ($data['adjustment_addition'] ?? 0);
        $deduction = (float) ($data['adjustment_deduction'] ?? 0);

        $data['base_salary'] = $baseSalary;
        $data['total_cashbon'] = $totalCashbon;
        $data['bpjs_allowance'] = $bpjs;
        $data['net_salary'] = $baseSalary - $totalCashbon - $bpjs + $addition - $deduction;

        return $data;
    }

    private function calculateMonthlyCashbon(Employee $employee, int $month, int $year): float
    {
        $currentDate = Carbon::create($year, $month, 1);
        $totalCashbon = 0.0;

        $cashbons = Cashbon::where('employee_id', $employee->id)
            ->where('status', 'paid')
            ->get();

        foreach ($cashbons as $cashbon) {
            $requestDate = Carbon::parse($cashbon->request_date);
            $installmentMonths = $cashbon->installment_months;

            if ($installmentMonths === null) {
                if ($requestDate->month == $month && $requestDate->year == $year) {
                    $totalCashbon += (float) $cashbon->amount;
                }
                continue;
            }

            $startDate = Carbon::create($requestDate->year, $requestDate->month, 1);
            $endDate = $startDate->copy()->addMonths($installmentMonths - 1)->endOfMonth();

            if ($currentDate->year == $startDate->year && $currentDate->month == $startDate->month) {
                $totalCashbon += ((float) $cashbon->amount / (int) $installmentMonths);
            } elseif ($currentDate->greaterThan($startDate) && $currentDate->lessThanOrEqualTo($endDate)) {
                $monthsDiff = $currentDate->diffInMonths($startDate);
                if ($monthsDiff < $installmentMonths) {
                    $totalCashbon += ((float) $cashbon->amount / (int) $installmentMonths);
                }
            }
        }

        return $totalCashbon;
    }
}

