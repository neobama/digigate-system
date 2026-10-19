<?php

namespace App\Observers;

use App\Models\BudgetRequest;
use App\Services\ActivityLogService;

class BudgetRequestObserver
{
    public function created(BudgetRequest $budgetRequest): void
    {
        try {
            ActivityLogService::logCreate($budgetRequest);
        } catch (\Exception $e) {
            \Log::error('Failed to log activity for budget request creation', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(BudgetRequest $budgetRequest): void
    {
        if (! $budgetRequest->wasChanged('status')) {
            return;
        }

        try {
            $oldValues = $budgetRequest->getOriginal();
            $newValues = $budgetRequest->getChanges();
            unset($oldValues['updated_at'], $newValues['updated_at']);

            if (! empty($newValues)) {
                ActivityLogService::logUpdate($budgetRequest, $oldValues, $newValues);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to log activity for budget request update', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(BudgetRequest $budgetRequest): void
    {
        try {
            ActivityLogService::logDelete($budgetRequest);
        } catch (\Exception $e) {
            \Log::error('Failed to log activity for budget request deletion', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
