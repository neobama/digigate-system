<?php

namespace App\Filament\Resources\SalaryPaymentResource\Pages;

use App\Filament\Resources\SalaryPaymentResource;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentAdjustment;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSalaryPayment extends EditRecord
{
    protected static string $resource = SalaryPaymentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var SalaryPayment $record */
        $record = $this->getRecord();

        $data['adjustments'] = $record->adjustments()
            ->get(['type', 'description', 'amount'])
            ->map(fn ($item) => [
                'type' => $item->type,
                'description' => $item->description,
                'amount' => (float) $item->amount,
            ])
            ->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $month = (int) $data['month'];
        $year = (int) $data['year'];

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

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $adjustments = $data['adjustments'] ?? [];
        unset($data['adjustments']);

        $record->update($data);

        $record->adjustments()->delete();

        foreach ($adjustments as $adjustment) {
            if (empty($adjustment['type']) || empty($adjustment['description']) || empty($adjustment['amount'])) {
                continue;
            }

            SalaryPaymentAdjustment::create([
                'salary_payment_id' => $record->id,
                'type' => $adjustment['type'],
                'description' => $adjustment['description'],
                'amount' => (float) $adjustment['amount'],
            ]);
        }

        return $record;
    }
}

