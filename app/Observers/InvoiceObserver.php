<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\ActivityLogService;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        ActivityLogService::logCreate($invoice);
    }

    public function updated(Invoice $invoice): void
    {
        $oldValues = $invoice->getOriginal();
        $newValues = $invoice->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);

        if (! empty($newValues)) {
            ActivityLogService::logUpdate($invoice, $oldValues, $newValues);
        }
    }

    public function deleted(Invoice $invoice): void
    {
        ActivityLogService::logDelete($invoice);
    }
}
