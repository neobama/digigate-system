<?php

namespace App\Observers;

use App\Models\BudgetRequest;
use App\Services\ActivityLogService;
use App\Services\ExpenseRecognitionService;
use App\Services\TelegramNotifier;

class BudgetRequestObserver
{
    public function __construct(
        private ExpenseRecognitionService $expenseRecognition,
        private TelegramNotifier $telegramNotifier,
    ) {}

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

        $this->expenseRecognition->ensureForBudgetRequest($budgetRequest);

        try {
            $this->telegramNotifier->paymentRequestCreated($budgetRequest);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (budget request created)', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(BudgetRequest $budgetRequest): void
    {
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

        if ($budgetRequest->wasChanged([
            'status',
            'amount',
            'budget_name',
            'paid_at',
            'realized_amount',
            'realization_notes',
            'realization_submitted_at',
            'realization_proof_images',
        ])) {
            $this->expenseRecognition->ensureForBudgetRequest($budgetRequest);
        }

        if ($budgetRequest->wasChanged('status')) {
            $this->telegramNotifier->paymentRequestStatusChanged($budgetRequest, $budgetRequest->status);
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
