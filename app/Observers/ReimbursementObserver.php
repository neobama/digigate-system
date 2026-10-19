<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Services\ActivityLogService;

class ReimbursementObserver
{
    public function created(Reimbursement $reimbursement): void
    {
        ActivityLogService::logCreate($reimbursement);
    }

    public function updated(Reimbursement $reimbursement): void
    {
        $oldValues = $reimbursement->getOriginal();
        $newValues = $reimbursement->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($reimbursement, $oldValues, $newValues);
        }
    }

    public function deleted(Reimbursement $reimbursement): void
    {
        ActivityLogService::logDelete($reimbursement);
    }
}
