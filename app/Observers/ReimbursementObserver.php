<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Services\ActivityLogService;
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
        // Log activity
        ActivityLogService::logCreate($reimbursement);
        
        $reimbursement->load('employee');
        $employee = $reimbursement->employee;
        
        if (!$employee) {
            return; // Skip if employee not found
        }
        
        $message = "📋 *Reimbursement Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Keperluan: {$reimbursement->purpose}\n";
        
        // Safely format date
        $expenseDate = $reimbursement->expense_date;
        if ($expenseDate instanceof \Carbon\Carbon) {
            $message .= "Tanggal: " . $expenseDate->format('d/m/Y') . "\n";
        } elseif (is_string($expenseDate)) {
            $message .= "Tanggal: " . \Carbon\Carbon::parse($expenseDate)->format('d/m/Y') . "\n";
        } else {
            $message .= "Tanggal: " . ($expenseDate ?? 'N/A') . "\n";
        }
        
        $message .= "Jumlah: Rp " . number_format($reimbursement->amount, 0, ',', '.') . "\n";
        $message .= "Status: " . ucfirst($reimbursement->status);
        
        if ($reimbursement->description) {
            $message .= "\nKeterangan: {$reimbursement->description}";
        }
        
        // Send notification to admin (don't fail if WhatsApp fails)
        try {
            $this->whatsapp->sendToAdmin($message);
        } catch (\Exception $e) {
            // Log error but don't stop the reimbursement creation process
            \Log::error('Failed to send WhatsApp notification to admin for new reimbursement', [
                'reimbursement_id' => $reimbursement->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Reimbursement "updated" event.
     */
    public function updated(Reimbursement $reimbursement): void
    {
        // Log activity
        $oldValues = $reimbursement->getOriginal();
        $newValues = $reimbursement->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);
        if (!empty($newValues)) {
            ActivityLogService::logUpdate($reimbursement, $oldValues, $newValues);
        }
        
        // Only notify if status changed
        if ($reimbursement->wasChanged('status')) {
            $reimbursement->load('employee');
            $employee = $reimbursement->employee;
            
            if (!$employee) {
                return; // Skip if employee not found
            }
            
            $newStatus = $reimbursement->status;
            
            // Notify employee if status is approved or paid
            if (in_array($newStatus, ['approved', 'paid']) && !empty($employee->phone_number)) {
                try {
                    $employeeMessage = "📋 *Update Reimbursement Anda*\n\n";
                    $employeeMessage .= "Keperluan: {$reimbursement->purpose}\n";
                    
                    // Safely format date
                    $expenseDate = $reimbursement->expense_date;
                    if ($expenseDate instanceof \Carbon\Carbon) {
                        $employeeMessage .= "Tanggal: " . $expenseDate->format('d/m/Y') . "\n";
                    } elseif (is_string($expenseDate)) {
                        $employeeMessage .= "Tanggal: " . \Carbon\Carbon::parse($expenseDate)->format('d/m/Y') . "\n";
                    }
                    
                    $employeeMessage .= "Jumlah: Rp " . number_format($reimbursement->amount, 0, ',', '.') . "\n";
                    
                    if ($newStatus === 'approved') {
                        $employeeMessage .= "✅ Status: *Disetujui*\n\n";
                        $employeeMessage .= "Reimbursement Anda telah disetujui dan akan segera diproses.";
                    } elseif ($newStatus === 'paid') {
                        $employeeMessage .= "✅ Status: *Sudah Dibayar*\n\n";
                        $employeeMessage .= "Reimbursement Anda sudah dibayar.";
                    }
                    
                    $this->whatsapp->sendMessage($employee->phone_number, $employeeMessage);
                } catch (\Exception $e) {
                    // Log error but don't stop the process
                    \Log::error('Failed to send WhatsApp notification to employee for reimbursement', [
                        'reimbursement_id' => $reimbursement->id,
                        'employee_id' => $employee->id,
                        'phone' => $employee->phone_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the Reimbursement "deleted" event.
     */
    public function deleted(Reimbursement $reimbursement): void
    {
        // Log activity
        ActivityLogService::logDelete($reimbursement);
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
