<?php

namespace App\Services;

use App\Models\Income;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class IncomeRecognitionService
{
    /**
     * Ensure a paid/delivered invoice has exactly one linked Income row.
     * If invoice leaves paid/delivered, remove the linked Income.
     */
    public function ensureForInvoice(Invoice $invoice): ?Income
    {
        $isRecognized = in_array($invoice->status, ['paid', 'delivered'], true);

        $existing = Income::query()->where('invoice_id', $invoice->id)->first();

        if (! $isRecognized) {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $description = sprintf(
            'Invoice #%s — %s',
            $invoice->invoice_number,
            $invoice->client_name
        );

        $incomeDate = $invoice->paid_date
            ? $invoice->paid_date->toDateString()
            : now()->toDateString();

        $amount = (float) $invoice->total_amount;

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
