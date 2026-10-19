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

        if ($cashbon->status === 'paid') {
            $this->expenseRecognition->ensureForCashbon($cashbon);
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
            $this->expenseRecognition->ensureForCashbon($cashbon);
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        ActivityLogService::logDelete($cashbon);
    }
}
