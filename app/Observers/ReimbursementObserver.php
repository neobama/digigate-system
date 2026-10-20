<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Services\ActivityLogService;
use App\Services\ExpenseRecognitionService;
use App\Services\TelegramNotifier;

class ReimbursementObserver
{
    public function __construct(
        private ExpenseRecognitionService $expenseRecognition,
        private TelegramNotifier $telegramNotifier,
    ) {}

    public function created(Reimbursement $reimbursement): void
    {
        // Telegram dulu — jangan sampai gagal karena log/expense.
        try {
            $this->telegramNotifier->paymentRequestCreated($reimbursement);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (reimbursement created)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            ActivityLogService::logCreate($reimbursement);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (reimbursement created)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->expenseRecognition->ensureForReimbursement($reimbursement);
        } catch (\Throwable $e) {
            \Log::error('Expense recognition gagal (reimbursement created)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Reimbursement $reimbursement): void
    {
        try {
            $oldValues = $reimbursement->getOriginal();
            $newValues = $reimbursement->getChanges();
            unset($oldValues['updated_at'], $newValues['updated_at']);

            if (! empty($newValues)) {
                ActivityLogService::logUpdate($reimbursement, $oldValues, $newValues);
            }
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (reimbursement updated)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($reimbursement->wasChanged(['status', 'amount', 'purpose', 'expense_date', 'proof_of_payment', 'paid_at'])) {
            try {
                $this->expenseRecognition->ensureForReimbursement($reimbursement);
            } catch (\Throwable $e) {
                \Log::error('Expense recognition gagal (reimbursement updated)', [
                    'reimbursement_id' => $reimbursement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($reimbursement->wasChanged('status')) {
            try {
                $this->telegramNotifier->paymentRequestStatusChanged($reimbursement, $reimbursement->status);
            } catch (\Throwable $e) {
                \Log::error('Telegram notify gagal (reimbursement updated)', [
                    'reimbursement_id' => $reimbursement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(Reimbursement $reimbursement): void
    {
        try {
            ActivityLogService::logDelete($reimbursement);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (reimbursement deleted)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
