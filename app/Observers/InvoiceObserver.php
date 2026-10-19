<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\ActivityLogService;
use App\Services\IncomeRecognitionService;

class InvoiceObserver
{
    public function __construct(
        private IncomeRecognitionService $incomeRecognition,
    ) {}

    public function created(Invoice $invoice): void
    {
        ActivityLogService::logCreate($invoice);

        if (in_array($invoice->status, ['paid', 'delivered'], true)) {
            $this->incomeRecognition->ensureForInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice): void
    {
        $oldValues = $invoice->getOriginal();
        $newValues = $invoice->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($invoice, $oldValues, $newValues);
        }

        // Sync income when status, amount, paid_date, or identity fields change
        if ($invoice->wasChanged(['status', 'total_amount', 'paid_date', 'invoice_number', 'client_name'])) {
            $this->incomeRecognition->ensureForInvoice($invoice);
        }
    }

    public function deleted(Invoice $invoice): void
    {
        ActivityLogService::logDelete($invoice);
        // incomes.invoice_id cascades on delete
    }
}
