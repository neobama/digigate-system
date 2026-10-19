<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Models\Expense;
use App\Services\ActivityLogService;

class CashbonObserver
{
    public function created(Cashbon $cashbon): void
    {
        ActivityLogService::logCreate($cashbon);

        if ($cashbon->status === 'paid') {
            $this->ensureExpenseRecorded($cashbon);
        }
    }

    public function updated(Cashbon $cashbon): void
    {
        if (! $cashbon->wasChanged('status')) {
            return;
        }

        $oldValues = $cashbon->getOriginal();
        $newValues = $cashbon->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($cashbon, $oldValues, $newValues);
        }

        if ($cashbon->status === 'paid') {
            $this->ensureExpenseRecorded($cashbon);
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        ActivityLogService::logDelete($cashbon);
    }

    private function ensureExpenseRecorded(Cashbon $cashbon): void
    {
        if ($cashbon->status !== 'paid') {
            return;
        }

        $cashbon->loadMissing('employee');
        $employee = $cashbon->employee;

        if (! $employee) {
            return;
        }

        if (Expense::query()->where('cashbon_id', $cashbon->id)->exists()) {
            return;
        }

        try {
            $description = $cashbon->reason.' - '.$employee->name;
            if ($cashbon->is_term_loan) {
                $description = 'Pinjaman term: '.$description;
            }
            if ($cashbon->installment_months) {
                $description .= ' (Cicilan: '.$cashbon->installment_months.' bulan)';
            }

            Expense::create([
                'cashbon_id' => $cashbon->id,
                'description' => $description,
                'expense_date' => $cashbon->request_date,
                'amount' => $cashbon->amount,
                'fund_source' => 'bank_perusahaan',
                'vendor_invoice_number' => null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create expense for cashbon', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
