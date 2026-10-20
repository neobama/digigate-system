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
        try {
            $this->telegramNotifier->paymentRequestCreated($cashbon);
        } catch (\Throwable $e) {
            \Log::error('Telegram notify gagal (cashbon created)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            ActivityLogService::logCreate($cashbon);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (cashbon created)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->expenseRecognition->ensureForCashbon($cashbon);
        } catch (\Throwable $e) {
            \Log::error('Expense recognition gagal (cashbon created)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Cashbon $cashbon): void
    {
        try {
            $oldValues = $cashbon->getOriginal();
            $newValues = $cashbon->getChanges();
            unset($oldValues['updated_at'], $newValues['updated_at']);

            if (! empty($newValues)) {
                ActivityLogService::logUpdate($cashbon, $oldValues, $newValues);
            }
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (cashbon updated)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($cashbon->wasChanged(['status', 'amount', 'reason', 'paid_at', 'installment_months', 'is_term_loan'])) {
            try {
                $this->expenseRecognition->ensureForCashbon($cashbon);
            } catch (\Throwable $e) {
                \Log::error('Expense recognition gagal (cashbon updated)', [
                    'cashbon_id' => $cashbon->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($cashbon->wasChanged('status')) {
            try {
                $this->telegramNotifier->paymentRequestStatusChanged($cashbon, $cashbon->status);
            } catch (\Throwable $e) {
                \Log::error('Telegram notify gagal (cashbon updated)', [
                    'cashbon_id' => $cashbon->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        try {
            ActivityLogService::logDelete($cashbon);
        } catch (\Throwable $e) {
            \Log::error('Activity log gagal (cashbon deleted)', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
