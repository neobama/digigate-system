<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Employee;
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
        //
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
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
        
        // Add assembly information
        if ($invoice->assemblies->isNotEmpty()) {
            $message .= "\n📦 *Perangkat yang perlu di-assembly:*\n";
            foreach ($invoice->assemblies as $assembly) {
                $message .= "• {$assembly->product_type}";
                if ($assembly->serial_number) {
                    $message .= " (SN: {$assembly->serial_number})";
                }
                $message .= "\n";
            }
        }
        
        // Send to all employees
        $phoneNumbers = $employees->pluck('phone_number')->filter()->toArray();
        $this->whatsapp->sendBulk($phoneNumbers, $message);
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        //
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
