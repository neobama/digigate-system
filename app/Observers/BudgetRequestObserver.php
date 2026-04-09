<?php

namespace App\Observers;

use App\Models\BudgetRequest;
use App\Services\ActivityLogService;
use App\Services\WhatsAppService;

class BudgetRequestObserver
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Handle the BudgetRequest "created" event.
     */
    public function created(BudgetRequest $budgetRequest): void
    {
        // Log activity (don't fail if logging fails)
        try {
            ActivityLogService::logCreate($budgetRequest);
        } catch (\Exception $e) {
            \Log::error('Failed to log activity for budget request creation', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        $budgetRequest->load('employee');
        $employee = $budgetRequest->employee;

        if (! $employee) {
            return; // Skip if employee not found
        }

        $message = "📋 *Request Anggaran Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Nama Anggaran: {$budgetRequest->budget_name}\n";
        $message .= "Detail: {$budgetRequest->budget_detail}\n";
        $message .= "Rekening: {$budgetRequest->recipient_account}\n";
        $message .= 'Nominal: Rp '.number_format($budgetRequest->amount, 0, ',', '.')."\n";

        // Safely format date
        $requestDate = $budgetRequest->request_date;
        if ($requestDate instanceof \Carbon\Carbon) {
            $message .= 'Tanggal Request: '.$requestDate->format('d/m/Y')."\n";
        } elseif (is_string($requestDate)) {
            $message .= 'Tanggal Request: '.\Carbon\Carbon::parse($requestDate)->format('d/m/Y')."\n";
        } else {
            $message .= 'Tanggal Request: '.($requestDate ?? 'N/A')."\n";
        }

        $message .= 'Status: '.ucfirst($budgetRequest->status);

        // Send notification to admin (don't fail if WhatsApp fails)
        try {
            $this->whatsapp->sendToAdmin($message);
        } catch (\Exception $e) {
            // Log error but don't stop the budget request creation process
            \Log::error('Failed to send WhatsApp notification to admin for new budget request', [
                'budget_request_id' => $budgetRequest->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the BudgetRequest "updated" event.
     */
    public function updated(BudgetRequest $budgetRequest): void
    {
        // Only notify if status changed
        if ($budgetRequest->wasChanged('status')) {
            $budgetRequest->load('employee');
            $employee = $budgetRequest->employee;

            if (! $employee) {
                return; // Skip if employee not found
            }

            $newStatus = $budgetRequest->status;

            // Log activity (don't fail if logging fails)
            try {
                $oldValues = $budgetRequest->getOriginal();
                $newValues = $budgetRequest->getChanges();
                unset($oldValues['updated_at'], $newValues['updated_at']);
                if (! empty($newValues)) {
                    ActivityLogService::logUpdate($budgetRequest, $oldValues, $newValues);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to log activity for budget request update', [
                    'budget_request_id' => $budgetRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Pengeluaran (Expense) dicatat setelah karyawan mengirim realisasi + bukti di panel Employee,
            // bukan otomatis saat paid (lihat BudgetRealizationService).

            // Notify employee if status is approved, rejected, or paid
            if (in_array($newStatus, ['approved', 'rejected', 'paid']) && ! empty($employee->phone_number)) {
                try {
                    $employeeMessage = "📋 *Update Request Anggaran Anda*\n\n";
                    $employeeMessage .= "Nama Anggaran: {$budgetRequest->budget_name}\n";
                    $employeeMessage .= 'Nominal: Rp '.number_format($budgetRequest->amount, 0, ',', '.')."\n";

                    if ($newStatus === 'approved') {
                        $employeeMessage .= "✅ Status: *Disetujui*\n\n";
                        $employeeMessage .= 'Request anggaran Anda telah disetujui.';
                    } elseif ($newStatus === 'rejected') {
                        $employeeMessage .= "❌ Status: *Ditolak*\n\n";
                        $employeeMessage .= 'Request anggaran Anda telah ditolak.';
                    } elseif ($newStatus === 'paid') {
                        $employeeMessage .= "✅ Status: *Sudah Dibayar*\n\n";
                        $employeeMessage .= "Anggaran sudah ditransfer ke: {$budgetRequest->recipient_account}\n\n";
                        $employeeMessage .= 'Setelah memakai dana, *wajib* input *realisasi* (nominal aktual + bukti pembelian) di menu Request Anggaran di aplikasi agar pengeluaran tercatat.';
                    }

                    $this->whatsapp->sendMessage($employee->phone_number, $employeeMessage);
                } catch (\Exception $e) {
                    // Log error but don't stop the process
                    \Log::error('Failed to send WhatsApp notification to employee for budget request', [
                        'budget_request_id' => $budgetRequest->id,
                        'employee_id' => $employee->id,
                        'phone' => $employee->phone_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the BudgetRequest "deleted" event.
     */
    public function deleted(BudgetRequest $budgetRequest): void
    {
        // Log activity (don't fail if logging fails)
        try {
            ActivityLogService::logDelete($budgetRequest);
        } catch (\Exception $e) {
            \Log::error('Failed to log activity for budget request deletion', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the BudgetRequest "restored" event.
     */
    public function restored(BudgetRequest $budgetRequest): void
    {
        //
    }

    /**
     * Handle the BudgetRequest "force deleted" event.
     */
    public function forceDeleted(BudgetRequest $budgetRequest): void
    {
        //
    }
}
