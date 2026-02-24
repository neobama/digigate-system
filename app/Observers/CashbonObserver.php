<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Models\Expense;
use App\Services\ActivityLogService;
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
        // Log activity
        ActivityLogService::logCreate($cashbon);
        
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
        
        // Send notification to admin (don't fail if WhatsApp fails)
        try {
            $this->whatsapp->sendToAdmin($message);
        } catch (\Exception $e) {
            // Log error but don't stop the cashbon creation process
            \Log::error('Failed to send WhatsApp notification to admin for new cashbon', [
                'cashbon_id' => $cashbon->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            
            if (!$employee) {
                return; // Skip if employee not found
            }
            
            $newStatus = $cashbon->status;
            
            // Log activity
            $oldValues = $cashbon->getOriginal();
            $newValues = $cashbon->getChanges();
            unset($oldValues['updated_at'], $newValues['updated_at']);
            if (!empty($newValues)) {
                ActivityLogService::logUpdate($cashbon, $oldValues, $newValues);
            }
            
            // Auto-create Expense record when status changes to "paid"
            if ($newStatus === 'paid') {
                // Check if expense already exists for this cashbon
                $existingExpense = Expense::where('cashbon_id', $cashbon->id)->first();
                
                if (!$existingExpense) {
                    try {
                        $description = $cashbon->reason . ' - ' . $employee->name;
                        if ($cashbon->installment_months) {
                            $description .= ' (Cicilan: ' . $cashbon->installment_months . ' bulan)';
                        }
                        
                        Expense::create([
                            'cashbon_id' => $cashbon->id,
                            'description' => $description,
                            'expense_date' => $cashbon->request_date,
                            'amount' => $cashbon->amount,
                            'fund_source' => 'bank_perusahaan', // Default untuk cashbon
                            'vendor_invoice_number' => null,
                        ]);
                    } catch (\Exception $e) {
                        // Log error but don't stop the process
                        \Log::error('Failed to create expense for cashbon', [
                            'cashbon_id' => $cashbon->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            // Notify employee if status is approved or paid
            if (in_array($newStatus, ['approved', 'paid']) && !empty($employee->phone_number)) {
                try {
                    $employeeMessage = "💰 *Update Cashbon Anda*\n\n";
                    $employeeMessage .= "Alasan: {$cashbon->reason}\n";
                    $employeeMessage .= "Jumlah: Rp " . number_format($cashbon->amount, 0, ',', '.') . "\n";
                    
                    if ($newStatus === 'approved') {
                        $employeeMessage .= "✅ Status: *Disetujui*\n\n";
                        if ($cashbon->installment_months) {
                            $employeeMessage .= "Cashbon akan dicicil selama {$cashbon->installment_months} bulan.";
                        } else {
                            $employeeMessage .= "Cashbon akan langsung dipotong di bulan pertama.";
                        }
                    } elseif ($newStatus === 'paid') {
                        $employeeMessage .= "✅ Status: *Sudah Dibayar*\n\n";
                        $employeeMessage .= "Cashbon Anda sudah dibayar.";
                    }
                    
                    $this->whatsapp->sendMessage($employee->phone_number, $employeeMessage);
                } catch (\Exception $e) {
                    // Log error but don't stop the process
                    \Log::error('Failed to send WhatsApp notification to employee for cashbon', [
                        'cashbon_id' => $cashbon->id,
                        'employee_id' => $employee->id,
                        'phone' => $employee->phone_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the Cashbon "deleted" event.
     */
    public function deleted(Cashbon $cashbon): void
    {
        // Log activity
        ActivityLogService::logDelete($cashbon);
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
