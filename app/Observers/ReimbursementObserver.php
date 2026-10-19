<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Services\ActivityLogService;
use App\Services\ExpenseRecognitionService;

class ReimbursementObserver
{
    public function __construct(
        private ExpenseRecognitionService $expenseRecognition,
    ) {}

    public function created(Reimbursement $reimbursement): void
    {
        ActivityLogService::logCreate($reimbursement);

        if ($reimbursement->status === 'paid') {
            $this->expenseRecognition->ensureForReimbursement($reimbursement);
        }
    }

    public function updated(Reimbursement $reimbursement): void
    {
        $oldValues = $reimbursement->getOriginal();
        $newValues = $reimbursement->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($reimbursement, $oldValues, $newValues);
        }

        if ($reimbursement->wasChanged('status') && $reimbursement->status === 'paid') {
            $this->expenseRecognition->ensureForReimbursement($reimbursement);
        }
    }

    public function deleted(Reimbursement $reimbursement): void
    {
        ActivityLogService::logDelete($reimbursement);
    }
}
