<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Services\WhatsAppService;

class ReimbursementObserver
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Handle the Reimbursement "created" event.
     */
    public function created(Reimbursement $reimbursement): void
    {
        $reimbursement->load('employee');
        $employee = $reimbursement->employee;
        
        $message = "📋 *Reimbursement Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Keperluan: {$reimbursement->purpose}\n";
        $message .= "Tanggal: " . $reimbursement->expense_date->format('d/m/Y') . "\n";
        $message .= "Jumlah: Rp " . number_format($reimbursement->amount, 0, ',', '.') . "\n";
        $message .= "Status: " . ucfirst($reimbursement->status);
        
        if ($reimbursement->description) {
            $message .= "\nKeterangan: {$reimbursement->description}";
        }
        
        $this->whatsapp->sendToAdmin($message);
    }

    /**
     * Handle the Reimbursement "updated" event.
     */
    public function updated(Reimbursement $reimbursement): void
    {
        // Only notify if status changed
        if ($reimbursement->wasChanged('status')) {
            $reimbursement->load('employee');
            $employee = $reimbursement->employee;
            
            $message = "📋 *Update Reimbursement*\n\n";
            $message .= "Karyawan: {$employee->name}\n";
            $message .= "Keperluan: {$reimbursement->purpose}\n";
            $message .= "Jumlah: Rp " . number_format($reimbursement->amount, 0, ',', '.') . "\n";
            $message .= "Status: " . ucfirst($reimbursement->status);
            
            $this->whatsapp->sendToAdmin($message);
        }
    }

    /**
     * Handle the Reimbursement "deleted" event.
     */
    public function deleted(Reimbursement $reimbursement): void
    {
        //
    }

    /**
     * Handle the Reimbursement "restored" event.
     */
    public function restored(Reimbursement $reimbursement): void
    {
        //
    }

    /**
     * Handle the Reimbursement "force deleted" event.
     */
    public function forceDeleted(Reimbursement $reimbursement): void
    {
        //
    }
}
