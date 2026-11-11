<?php

namespace App\Services;

use App\Models\Income;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class IncomeRecognitionService
{
    /**
     * Sync linked Income to cash received (amount_paid).
     * Recognized for partially_paid / paid / delivered when amount_paid > 0.
     */
    public function ensureForInvoice(Invoice $invoice): ?Income
    {
        $amountPaid = round((float) $invoice->amount_paid, 2);
        $isRecognized = $amountPaid > 0
            && in_array($invoice->status, ['partially_paid', 'paid', 'delivered'], true);

        $existing = Income::query()->where('invoice_id', $invoice->id)->first();

        if (! $isRecognized) {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $description = sprintf(
            'Invoice #%s — %s%s',
            $invoice->invoice_number,
            $invoice->client_name,
            $invoice->status === 'partially_paid' ? ' (pembayaran sebagian)' : ''
        );

        $incomeDate = $invoice->paid_date
            ? $invoice->paid_date->toDateString()
            : now()->toDateString();

        $amount = $amountPaid;

        try {
            if ($existing) {
                $existing->update([
                    'description' => $description,
                    'income_date' => $incomeDate,
                    'amount' => $amount,
                ]);

                return $existing->fresh();
            }

            return Income::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'income_date' => $incomeDate,
                'amount' => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync income for invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
