<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Services\WhatsAppService;

class CashbonObserver
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Handle the Cashbon "created" event.
     */
    public function created(Cashbon $cashbon): void
    {
        $cashbon->load('employee');
        $employee = $cashbon->employee;
        
        if (!$employee) {
            return; // Skip if employee not found
        }
        
        $message = "💰 *Cashbon Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Alasan: {$cashbon->reason}\n";
        
        // Safely format date
        $requestDate = $cashbon->request_date;
        if ($requestDate instanceof \Carbon\Carbon) {
            $message .= "Tanggal Request: " . $requestDate->format('d/m/Y') . "\n";
        } elseif (is_string($requestDate)) {
            $message .= "Tanggal Request: " . \Carbon\Carbon::parse($requestDate)->format('d/m/Y') . "\n";
        } else {
            $message .= "Tanggal Request: " . ($requestDate ?? 'N/A') . "\n";
        }
        
        $message .= "Jumlah: Rp " . number_format($cashbon->amount, 0, ',', '.') . "\n";
        
        if ($cashbon->installment_months) {
            $message .= "Cicilan: {$cashbon->installment_months} bulan\n";
        }
        
        $message .= "Status: " . ucfirst($cashbon->status);
        
        $this->whatsapp->sendToAdmin($message);
    }

    /**
     * Handle the Cashbon "updated" event.
     */
    public function updated(Cashbon $cashbon): void
    {
        // Only notify if status changed
        if ($cashbon->wasChanged('status')) {
            $cashbon->load('employee');
            $employee = $cashbon->employee;
            
            $message = "💰 *Update Cashbon*\n\n";
            $message .= "Karyawan: {$employee->name}\n";
            $message .= "Alasan: {$cashbon->reason}\n";
            $message .= "Jumlah: Rp " . number_format($cashbon->amount, 0, ',', '.') . "\n";
            $message .= "Status: " . ucfirst($cashbon->status);
            
            $this->whatsapp->sendToAdmin($message);
        }
    }

    /**
     * Handle the Cashbon "deleted" event.
     */
    public function deleted(Cashbon $cashbon): void
    {
        //
    }

    /**
     * Handle the Cashbon "restored" event.
     */
    public function restored(Cashbon $cashbon): void
    {
        //
    }

    /**
     * Handle the Cashbon "force deleted" event.
     */
    public function forceDeleted(Cashbon $cashbon): void
    {
        //
    }
}
