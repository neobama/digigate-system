<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Services\ActivityLogService;
use App\Services\ExpenseRecognitionService;

class CashbonObserver
{
    public function __construct(
        private ExpenseRecognitionService $expenseRecognition,
    ) {}

    public function created(Cashbon $cashbon): void
    {
        ActivityLogService::logCreate($cashbon);
        $this->expenseRecognition->ensureForCashbon($cashbon);
    }

    public function updated(Cashbon $cashbon): void
    {
        $oldValues = $cashbon->getOriginal();
        $newValues = $cashbon->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($cashbon, $oldValues, $newValues);
        }

        if ($cashbon->wasChanged(['status', 'amount', 'reason', 'paid_at', 'installment_months', 'is_term_loan'])) {
            $this->expenseRecognition->ensureForCashbon($cashbon);
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        ActivityLogService::logDelete($cashbon);
        // expenses.cashbon_id cascades on delete
    }
}
