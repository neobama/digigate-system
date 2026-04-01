<?php

namespace App\Filament\Resources\SalaryPaymentResource\Pages;

use App\Filament\Resources\SalaryPaymentResource;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentAdjustment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSalaryPayment extends CreateRecord
{
    protected static string $resource = SalaryPaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $month = (int) $data['month'];
        $year = (int) $data['year'];

        $exists = SalaryPayment::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => 'Slip gaji untuk karyawan dan periode ini sudah pernah digenerate.',
            ]);
        }

        $totalCashbon = SalaryPaymentResource::calculateMonthlyCashbon($employee, $month, $year);
        $baseSalary = (float) $employee->base_salary;
        $bpjs = (float) $employee->bpjs_allowance;
        $adjustments = $data['adjustments'] ?? [];
        $addition = (float) collect($adjustments)->where('type', 'addition')->sum('amount');
        $deduction = (float) collect($adjustments)->where('type', 'deduction')->sum('amount');

        $data['base_salary'] = $baseSalary;
        $data['total_cashbon'] = $totalCashbon;
        $data['bpjs_allowance'] = $bpjs;
        $data['adjustment_addition'] = $addition;
        $data['adjustment_deduction'] = $deduction;
        $data['adjustment_note'] = null;
        $data['net_salary'] = $baseSalary - $totalCashbon - $bpjs + $addition - $deduction;
        $data['status'] = 'draft';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $adjustments = $data['adjustments'] ?? [];
        unset($data['adjustments']);

        /** @var SalaryPayment $salaryPayment */
        $salaryPayment = static::getModel()::create($data);

        foreach ($adjustments as $adjustment) {
            if (empty($adjustment['type']) || empty($adjustment['description']) || empty($adjustment['amount'])) {
                continue;
            }

            SalaryPaymentAdjustment::create([
                'salary_payment_id' => $salaryPayment->id,
                'type' => $adjustment['type'],
                'description' => $adjustment['description'],
                'amount' => (float) $adjustment['amount'],
            ]);
        }

        return $salaryPayment;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Slip gaji berhasil digenerate')
            ->success()
            ->send();
    }

}

