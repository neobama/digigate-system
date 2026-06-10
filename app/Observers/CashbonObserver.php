<?php

namespace App\Observers;

use App\Models\Cashbon;
use App\Models\Expense;
use App\Services\ActivityLogService;
use App\Services\WhatsAppService;

class CashbonObserver
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function created(Cashbon $cashbon): void
    {
        ActivityLogService::logCreate($cashbon);

        $cashbon->load('employee');
        $employee = $cashbon->employee;

        if (! $employee) {
            return;
        }

        if ($cashbon->status === 'paid') {
            $this->ensureExpenseRecorded($cashbon);
        }

        if ($cashbon->is_term_loan) {
            $this->notifyEmployeeTermLoan($cashbon, $employee);

            return;
        }

        $message = "💰 *Cashbon Baru*\n\n";
        $message .= "Karyawan: {$employee->name}\n";
        $message .= "Alasan: {$cashbon->reason}\n";
        $message .= 'Tanggal Request: '.$this->formatRequestDate($cashbon)."\n";
        $message .= 'Jumlah: Rp '.number_format((float) $cashbon->amount, 0, ',', '.')."\n";

        if ($cashbon->installment_months) {
            $message .= "Cicilan: {$cashbon->installment_months} bulan\n";
        }

        $message .= 'Status: '.ucfirst($cashbon->status);

        try {
            $this->whatsapp->sendToAdmin($message);
        } catch (\Exception $e) {
            \Log::error('Failed to send WhatsApp notification to admin for new cashbon', [
                'cashbon_id' => $cashbon->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Cashbon $cashbon): void
    {
        if (! $cashbon->wasChanged('status')) {
            return;
        }

        $cashbon->load('employee');
        $employee = $cashbon->employee;

        if (! $employee) {
            return;
        }

        $newStatus = $cashbon->status;

        $oldValues = $cashbon->getOriginal();
        $newValues = $cashbon->getChanges();
        unset($oldValues['updated_at'], $newValues['updated_at']);
        if (! empty($newValues)) {
            ActivityLogService::logUpdate($cashbon, $oldValues, $newValues);
        }

        if ($newStatus === 'paid') {
            $this->ensureExpenseRecorded($cashbon);
        }

        if (in_array($newStatus, ['approved', 'paid'], true) && ! empty($employee->phone_number) && ! $cashbon->is_term_loan) {
            try {
                $employeeMessage = "💰 *Update Cashbon Anda*\n\n";
                $employeeMessage .= "Alasan: {$cashbon->reason}\n";
                $employeeMessage .= 'Jumlah: Rp '.number_format((float) $cashbon->amount, 0, ',', '.')."\n";

                if ($newStatus === 'approved') {
                    $employeeMessage .= "✅ Status: *Disetujui*\n\n";
                    if ($cashbon->installment_months) {
                        $employeeMessage .= "Cashbon akan dicicil selama {$cashbon->installment_months} bulan.";
                    } else {
                        $employeeMessage .= 'Cashbon akan langsung dipotong di bulan pertama.';
                    }
                } elseif ($newStatus === 'paid') {
                    $employeeMessage .= "✅ Status: *Sudah Dibayar*\n\n";
                    $employeeMessage .= 'Cashbon Anda sudah dibayar.';
                }

                $this->whatsapp->sendMessage($employee->phone_number, $employeeMessage);
            } catch (\Exception $e) {
                \Log::error('Failed to send WhatsApp notification to employee for cashbon', [
                    'cashbon_id' => $cashbon->id,
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(Cashbon $cashbon): void
    {
        ActivityLogService::logDelete($cashbon);
    }

    private function ensureExpenseRecorded(Cashbon $cashbon): void
    {
        if ($cashbon->status !== 'paid') {
            return;
        }

        $cashbon->loadMissing('employee');
        $employee = $cashbon->employee;

        if (! $employee) {
            return;
        }

        if (Expense::query()->where('cashbon_id', $cashbon->id)->exists()) {
            return;
        }

        try {
            $description = $cashbon->reason.' - '.$employee->name;
            if ($cashbon->is_term_loan) {
                $description = 'Pinjaman term: '.$description;
            }
            if ($cashbon->installment_months) {
                $description .= ' (Cicilan: '.$cashbon->installment_months.' bulan)';
            }

            Expense::create([
                'cashbon_id' => $cashbon->id,
                'description' => $description,
                'expense_date' => $cashbon->request_date,
                'amount' => $cashbon->amount,
                'fund_source' => 'bank_perusahaan',
                'vendor_invoice_number' => null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create expense for cashbon', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyEmployeeTermLoan(Cashbon $cashbon, $employee): void
    {
        if (empty($employee->phone_number)) {
            return;
        }

        try {
            $message = "💰 *Pinjaman Term*\n\n";
            $message .= "Keterangan: {$cashbon->reason}\n";
            $message .= 'Total: Rp '.number_format((float) $cashbon->amount, 0, ',', '.')."\n";
            $message .= 'Mulai potong gaji: '.$this->formatRequestDate($cashbon)."\n";

            if ($cashbon->installment_months) {
                $perMonth = (float) $cashbon->amount / (int) $cashbon->installment_months;
                $message .= "Cicilan: {$cashbon->installment_months} bulan (Rp ".number_format($perMonth, 0, ',', '.')."/bulan)\n";
            } else {
                $message .= "Potongan: sekali di bulan pertama.\n";
            }

            $message .= "\nRincian potongan akan muncul di slip gaji.";

            $this->whatsapp->sendMessage($employee->phone_number, $message);
        } catch (\Exception $e) {
            \Log::error('Failed to send WhatsApp for term loan', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatRequestDate(Cashbon $cashbon): string
    {
        $requestDate = $cashbon->request_date;

        if ($requestDate instanceof \Carbon\Carbon) {
            return $requestDate->format('d/m/Y');
        }

        if (is_string($requestDate)) {
            return \Carbon\Carbon::parse($requestDate)->format('d/m/Y');
        }

        return (string) ($requestDate ?? 'N/A');
    }
}
