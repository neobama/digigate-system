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
            $this->telegramNotifier->paymentRequestCreated($budgetRequest);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (budget request created)', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            ActivityLogService::logCreate($budgetRequest);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (budget request created)', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->expenseRecognition->ensureForBudgetRequest($budgetRequest);
        } catch (\Throwable $e) {
            \Log::error('Expense recognition gagal (budget request created)', [
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
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (budget request updated)', [
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
            try {
                $this->expenseRecognition->ensureForBudgetRequest($budgetRequest);
            } catch (\Throwable $e) {
                \Log::error('Expense recognition gagal (budget request updated)', [
                    'budget_request_id' => $budgetRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($budgetRequest->wasChanged('status')) {
            try {
                $this->telegramNotifier->paymentRequestStatusChanged($budgetRequest, $budgetRequest->status);
            } catch (\Throwable $e) {
                \Log::error('Telegram notify gagal (budget request updated)', [
                    'budget_request_id' => $budgetRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(BudgetRequest $budgetRequest): void
    {
        try {
            ActivityLogService::logDelete($budgetRequest);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (budget request deleted)', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
