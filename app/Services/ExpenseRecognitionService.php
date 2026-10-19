<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Cashbon;
use App\Models\Expense;
use App\Models\Reimbursement;
use App\Models\SalaryPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseRecognitionService
{
    /**
     * Ensure a paid cashbon has exactly one linked Expense.
     * If cashbon leaves paid, remove the linked Expense.
     */
    public function ensureForCashbon(Cashbon $cashbon): ?Expense
    {
        $existing = Expense::query()->where('cashbon_id', $cashbon->id)->first();

        if ($cashbon->status !== 'paid') {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $cashbon->loadMissing('employee');
        $employee = $cashbon->employee;
        if (! $employee) {
            return null;
        }

        $description = $cashbon->reason.' - '.$employee->name;
        if ($cashbon->is_term_loan) {
            $description = 'Pinjaman term: '.$description;
        }
        if ($cashbon->installment_months) {
            $description .= ' (Cicilan: '.$cashbon->installment_months.' bulan)';
        }

        $expenseDate = $cashbon->paid_at
            ? $cashbon->paid_at->toDateString()
            : ($cashbon->request_date?->toDateString() ?? now()->toDateString());

        $payload = [
            'description' => $description,
            'expense_date' => $expenseDate,
            'amount' => $cashbon->amount,
            'fund_source' => 'bank_perusahaan',
            'vendor_invoice_number' => null,
        ];

        try {
            if ($existing) {
                $existing->update($payload);

                return $existing->fresh();
            }

            return Expense::create([
                'cashbon_id' => $cashbon->id,
                ...$payload,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync expense for cashbon', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ensure a paid budget request has exactly one linked Expense.
     * If budget leaves paid, remove the linked Expense.
     */
    public function ensureForBudgetRequest(BudgetRequest $budgetRequest): ?Expense
    {
        $existing = Expense::query()->where('budget_request_id', $budgetRequest->id)->first();

        if ($budgetRequest->status !== 'paid') {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $budgetRequest->loadMissing('employee');
        $employeeName = $budgetRequest->employee?->name ?? 'Karyawan';

        $amount = $budgetRequest->realization_submitted_at !== null
            && $budgetRequest->realized_amount !== null
            ? (float) $budgetRequest->realized_amount
            : (float) $budgetRequest->amount;

        $expenseDate = $budgetRequest->paid_at
            ? $budgetRequest->paid_at->toDateString()
            : ($budgetRequest->request_date?->toDateString() ?? now()->toDateString());

        $description = $budgetRequest->budget_name.' — '.$employeeName;
        if ($budgetRequest->realization_submitted_at !== null) {
            $notes = trim((string) ($budgetRequest->realization_notes ?? ''));
            $description .= $notes !== '' ? ' | '.$notes : ' | Realisasi anggaran';
        } else {
            $description .= ' | Anggaran dibayar (menunggu realisasi)';
        }

        $proof = null;
        if (is_array($budgetRequest->realization_proof_images) && $budgetRequest->realization_proof_images !== []) {
            $proof = $budgetRequest->realization_proof_images[0];
        }

        $payload = [
            'description' => $description,
            'expense_date' => $expenseDate,
            'amount' => $amount,
            'fund_source' => 'bank_perusahaan',
            'proof_of_payment' => $proof,
            'vendor_invoice_number' => null,
        ];

        try {
            if ($existing) {
                // Keep realization expense_date / proof if already realized and caller overrides via BudgetRealizationService
                $update = $payload;
                if ($budgetRequest->realization_submitted_at !== null && $existing->expense_date) {
                    unset($update['expense_date']);
                }
                if ($budgetRequest->realization_submitted_at !== null && $existing->proof_of_payment && ! $proof) {
                    unset($update['proof_of_payment']);
                }

                $existing->update($update);

                return $existing->fresh();
            }

            return Expense::create([
                'budget_request_id' => $budgetRequest->id,
                ...$payload,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync expense for budget request', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ensure a paid reimbursement has exactly one linked Expense.
     * If reimbursement leaves paid, remove the linked Expense.
     */
    public function ensureForReimbursement(Reimbursement $reimbursement): ?Expense
    {
        $existing = Expense::query()->where('reimbursement_id', $reimbursement->id)->first();

        if ($reimbursement->status !== 'paid') {
            if ($existing) {
                $existing->delete();
            }

            return null;
        }

        $reimbursement->loadMissing('employee');
        $employee = $reimbursement->employee;
        if (! $employee) {
            return null;
        }

        $payload = [
            'description' => $reimbursement->purpose.' - '.$employee->name,
            'expense_date' => $reimbursement->expense_date,
            'amount' => $reimbursement->amount,
            'fund_source' => 'bank_perusahaan',
            'proof_of_payment' => $reimbursement->proof_of_payment,
            'vendor_invoice_number' => null,
        ];

        try {
            if ($existing) {
                $existing->update($payload);

                return $existing->fresh();
            }

            return Expense::create([
                'reimbursement_id' => $reimbursement->id,
                ...$payload,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync expense for reimbursement', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureForSalaryPayment(SalaryPayment $salaryPayment): ?Expense
    {
        if ($salaryPayment->status !== 'paid') {
            $this->clearSalaryExpense($salaryPayment);

            return null;
        }

        if ($salaryPayment->expense_id) {
            $linked = Expense::query()->find($salaryPayment->expense_id);
            if ($linked) {
                $salaryPayment->loadMissing('employee');
                $linked->update([
                    'description' => sprintf(
                        'Pembayaran gaji %s periode %02d/%s',
                        $salaryPayment->employee?->name ?? 'Karyawan',
                        $salaryPayment->month,
                        $salaryPayment->year
                    ),
                    'fund_source' => $salaryPayment->fund_source,
                    'amount' => $salaryPayment->net_salary,
                    'account_code' => 'GAJI',
                ]);

                return $linked->fresh();
            }
        }

        $salaryPayment->loadMissing('employee');

        return DB::transaction(function () use ($salaryPayment) {
            $salaryPayment->refresh();

            if ($salaryPayment->expense_id) {
                $linked = Expense::query()->find($salaryPayment->expense_id);
                if ($linked) {
                    return $linked;
                }
            }

            $expense = Expense::create([
                'description' => sprintf(
                    'Pembayaran gaji %s periode %02d/%s',
                    $salaryPayment->employee?->name ?? 'Karyawan',
                    $salaryPayment->month,
                    $salaryPayment->year
                ),
                'fund_source' => $salaryPayment->fund_source,
                'expense_date' => $salaryPayment->paid_at
                    ? $salaryPayment->paid_at->toDateString()
                    : now()->toDateString(),
                'amount' => $salaryPayment->net_salary,
                'vendor_invoice_number' => null,
                'account_code' => 'GAJI',
                'proof_of_payment' => null,
            ]);

            $salaryPayment->update([
                'expense_id' => $expense->id,
            ]);

            return $expense;
        });
    }

    public function clearSalaryExpense(SalaryPayment $salaryPayment): void
    {
        if (! $salaryPayment->expense_id) {
            return;
        }

        $expenseId = $salaryPayment->expense_id;

        $salaryPayment->update(['expense_id' => null]);
        Expense::query()->where('id', $expenseId)->delete();
    }
}
