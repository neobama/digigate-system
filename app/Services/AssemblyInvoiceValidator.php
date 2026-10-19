<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class AssemblyInvoiceValidator
{
    public static function ensureCanCreateAssembly(string $invoiceId, ?string $excludeAssemblyId = null): Invoice
    {
        $invoice = Invoice::query()
            ->withCount('assemblies')
            ->findOrFail($invoiceId);

        if ($invoice->status !== 'paid') {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice harus berstatus Paid sebelum dirakit.',
            ]);
        }

        $remaining = $invoice->remainingAssemblySlots();

        if ($excludeAssemblyId) {
            $current = $invoice->assemblies()->where('id', $excludeAssemblyId)->exists();
            if ($current) {
                $remaining++;
            }
        }

        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice ini sudah selesai dirakit. Kuota perangkat: '.$invoice->expectedAssemblyCount().'.',
            ]);
        }

        return $invoice;
    }
}
