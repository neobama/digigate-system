<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Employee;
use App\Services\ActivityLogService;
use App\Services\WhatsAppService;

class InvoiceObserver
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        // Log activity
        ActivityLogService::logCreate($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        // Log activity
        $oldValues = $invoice->getOriginal();
        $newValues = $invoice->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);
        if (!empty($newValues)) {
            ActivityLogService::logUpdate($invoice, $oldValues, $newValues);
        }
        
        // Only notify if status changed to 'paid'
        if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
            $this->notifyEmployeesAboutPaidInvoice($invoice);
        }
    }

    /**
     * Notify all employees about paid invoice
     */
    protected function notifyEmployeesAboutPaidInvoice(Invoice $invoice): void
    {
        $invoice->load('assemblies');
        
        // Get all active employees with phone numbers
        $employees = Employee::where('is_active', true)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->get();
        
        if ($employees->isEmpty()) {
            return;
        }
        
        $message = "✅ *Invoice Sudah Dibayar*\n\n";
        $message .= "Invoice: {$invoice->invoice_number}\n";
        $message .= "Client: {$invoice->client_name}\n";
        $message .= "Total: Rp " . number_format($invoice->total_amount, 0, ',', '.') . "\n";
        
        // Add invoice items (perangkat yang dibeli)
        if (!empty($invoice->items) && is_array($invoice->items)) {
            $message .= "\n📦 *Detail Perangkat:*\n";
            foreach ($invoice->items as $index => $item) {
                $itemName = $item['name'] ?? 'Perangkat ' . ($index + 1);
                $quantity = $item['quantity'] ?? 1;
                $message .= "• {$itemName} (Qty: {$quantity})\n";
            }
        }
        
        // Add assembly information (perangkat yang perlu di-assembly)
        if ($invoice->assemblies->isNotEmpty()) {
            $message .= "\n🔧 *Perangkat yang perlu di-assembly:*\n";
            foreach ($invoice->assemblies as $assembly) {
                $message .= "• {$assembly->product_type}";
                if ($assembly->serial_number) {
                    $message .= " (SN: {$assembly->serial_number})";
                }
                if ($assembly->sn_details && is_array($assembly->sn_details) && !empty($assembly->sn_details)) {
                    $snDetails = implode(', ', array_filter($assembly->sn_details));
                    if ($snDetails) {
                        $message .= "\n  Detail SN: {$snDetails}";
                    }
                }
                $message .= "\n";
            }
        }
        
        $message .= "\n⚠️ *Segera lakukan assembly untuk perangkat di atas!*";
        
        // Send to all employees
        $phoneNumbers = $employees->pluck('phone_number')->filter()->toArray();
        $this->whatsapp->sendBulk($phoneNumbers, $message);
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        // Log activity
        ActivityLogService::logDelete($invoice);
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
