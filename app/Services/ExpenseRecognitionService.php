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
    public function ensureForCashbon(Cashbon $cashbon): ?Expense
    {
        if ($cashbon->status !== 'paid') {
            return null;
        }

        $existing = Expense::query()->where('cashbon_id', $cashbon->id)->first();
        if ($existing) {
            return $existing;
        }

        $cashbon->loadMissing('employee');
        $employee = $cashbon->employee;
        if (! $employee) {
            return null;
        }

        try {
            $description = $cashbon->reason.' - '.$employee->name;
            if ($cashbon->is_term_loan) {
                $description = 'Pinjaman term: '.$description;
            }
            if ($cashbon->installment_months) {
                $description .= ' (Cicilan: '.$cashbon->installment_months.' bulan)';
            }

            return Expense::create([
                'cashbon_id' => $cashbon->id,
                'description' => $description,
                'expense_date' => $cashbon->request_date,
                'amount' => $cashbon->amount,
                'fund_source' => 'bank_perusahaan',
                'vendor_invoice_number' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create expense for cashbon', [
                'cashbon_id' => $cashbon->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureForBudgetRequest(BudgetRequest $budgetRequest): ?Expense
    {
        if ($budgetRequest->status !== 'paid') {
            return null;
        }

        $existing = Expense::query()->where('budget_request_id', $budgetRequest->id)->first();
        if ($existing) {
            return $existing;
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

        try {
            return Expense::create([
                'budget_request_id' => $budgetRequest->id,
                'description' => $description,
                'expense_date' => $expenseDate,
                'amount' => $amount,
                'fund_source' => 'bank_perusahaan',
                'proof_of_payment' => $proof,
                'vendor_invoice_number' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create expense for budget request', [
                'budget_request_id' => $budgetRequest->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureForReimbursement(Reimbursement $reimbursement): ?Expense
    {
        if ($reimbursement->status !== 'paid') {
            return null;
        }

        $existing = Expense::query()->where('reimbursement_id', $reimbursement->id)->first();
        if ($existing) {
            return $existing;
        }

        $reimbursement->loadMissing('employee');
        $employee = $reimbursement->employee;
        if (! $employee) {
            return null;
        }

        try {
            return Expense::create([
                'reimbursement_id' => $reimbursement->id,
                'description' => $reimbursement->purpose.' - '.$employee->name,
                'expense_date' => $reimbursement->expense_date,
                'amount' => $reimbursement->amount,
                'fund_source' => 'bank_perusahaan',
                'proof_of_payment' => $reimbursement->proof_of_payment,
                'vendor_invoice_number' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create expense for reimbursement', [
                'reimbursement_id' => $reimbursement->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function ensureForSalaryPayment(SalaryPayment $salaryPayment): ?Expense
    {
        if ($salaryPayment->expense_id) {
            $linked = Expense::query()->find($salaryPayment->expense_id);
            if ($linked) {
                return $linked;
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
                'expense_date' => now()->toDateString(),
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
}
