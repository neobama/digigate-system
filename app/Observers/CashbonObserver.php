<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Services\ActivityLogService;
use App\Services\ExpenseRecognitionService;
use App\Services\TelegramNotifier;

class CashbonObserver
{
    public function __construct(
        private ExpenseRecognitionService $expenseRecognition,
        private TelegramNotifier $telegramNotifier,
    ) {}

    public function created(Cashbon $cashbon): void
    {
        ActivityLogService::logCreate($cashbon);
        $this->expenseRecognition->ensureForCashbon($cashbon);

        try {
            $this->telegramNotifier->paymentRequestCreated($cashbon);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (cashbon created)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
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

        if ($cashbon->wasChanged('status')) {
            $this->telegramNotifier->paymentRequestStatusChanged($cashbon, $cashbon->status);
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        ActivityLogService::logDelete($cashbon);
    }
}
