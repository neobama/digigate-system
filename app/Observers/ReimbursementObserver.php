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
        ActivityLogService::logCreate($reimbursement);
        $this->expenseRecognition->ensureForReimbursement($reimbursement);

        try {
            $this->telegramNotifier->paymentRequestCreated($reimbursement);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (reimbursement created)', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);
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

        if ($reimbursement->wasChanged(['status', 'amount', 'purpose', 'expense_date', 'proof_of_payment', 'paid_at'])) {
            $this->expenseRecognition->ensureForReimbursement($reimbursement);
        }

        if ($reimbursement->wasChanged('status')) {
            $this->telegramNotifier->paymentRequestStatusChanged($reimbursement, $reimbursement->status);
        }
    }

    public function deleted(Reimbursement $reimbursement): void
    {
        ActivityLogService::logDelete($reimbursement);
    }
}
