<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoicePaymentService
{
    public function __construct(
        private IncomeRecognitionService $incomeRecognition,
    ) {}

    /**
     * @param  array{amount: float|int|string, paid_at?: mixed, method?: ?string, reference?: ?string, notes?: ?string, created_by?: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): InvoicePayment
    {
        if (in_array($invoice->status, ['cancelled', 'delivered'], true)) {
            throw new InvalidArgumentException('Invoice yang dibatalkan atau sudah delivered tidak bisa menerima pembayaran baru.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
        }

        $remaining = $this->remainingAmount($invoice);
        if ($amount > $remaining + 0.009) {
            throw new InvalidArgumentException(
                'Nominal melebihi sisa tagihan (Rp '.number_format($remaining, 0, ',', '.').').'
            );
        }

        return DB::transaction(function () use ($invoice, $data, $amount) {
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            $this->syncInvoiceFromPayments($invoice->fresh('payments'));

            return $payment;
        });
    }

    public function markFullyPaid(Invoice $invoice, ?string $paidAt = null, ?string $notes = null): ?InvoicePayment
    {
        $remaining = $this->remainingAmount($invoice);
        if ($remaining <= 0.009) {
            if ($invoice->status === 'proforma' || $invoice->status === 'partially_paid') {
                $this->syncInvoiceFromPayments($invoice);
            }

            return null;
        }

        return $this->recordPayment($invoice, [
            'amount' => $remaining,
            'paid_at' => $paidAt ?? now()->toDateString(),
            'method' => 'full_settlement',
            'notes' => $notes ?? 'Pelunasan sisa tagihan',
            'created_by' => auth()->id(),
        ]);
    }

    public function deletePayment(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;
        if (! $invoice) {
            $payment->delete();

            return;
        }

        if ($invoice->status === 'delivered') {
            throw new InvalidArgumentException('Tidak bisa menghapus pembayaran invoice yang sudah delivered.');
        }

        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();
            $this->syncInvoiceFromPayments($invoice->fresh('payments'));
        });
    }

    public function remainingAmount(Invoice $invoice): float
    {
        $total = round((float) $invoice->total_amount, 2);
        $paid = round((float) ($invoice->amount_paid ?? $invoice->payments()->sum('amount')), 2);

        return max(0, round($total - $paid, 2));
    }

    public function syncInvoiceFromPayments(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }

        $wasDelivered = $invoice->status === 'delivered';
        $amountPaid = round((float) $invoice->payments()->sum('amount'), 2);
        $total = round((float) $invoice->total_amount, 2);
        $firstPaidAt = $invoice->payments()->orderBy('paid_at')->value('paid_at');

        $status = $invoice->status;
        if (! $wasDelivered) {
            if ($amountPaid <= 0) {
                $status = 'proforma';
            } elseif ($amountPaid + 0.009 >= $total) {
                $status = 'paid';
            } else {
                $status = 'partially_paid';
            }
        }

        $invoice->forceFill([
            'amount_paid' => $amountPaid,
            'status' => $status,
            'paid_date' => $amountPaid > 0 ? ($firstPaidAt ?: now()->toDateString()) : null,
        ])->save();

        $this->incomeRecognition->ensureForInvoice($invoice->fresh());

        return $invoice->fresh();
    }
}
