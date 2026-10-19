<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class BudgetRealizationService
{
    /**
     * @param  array{realized_amount: float|int|string, realization_notes?: string|null, expense_date?: string|null, realization_proof_images: array<int, string>|string|null}  $data
     */
    public static function submit(BudgetRequest $budgetRequest, array $data): Expense
    {
        if ($budgetRequest->status !== 'paid') {
            throw new \InvalidArgumentException('Hanya request yang sudah dibayar (paid) yang bisa direalisasi.');
        }

        if ($budgetRequest->realization_submitted_at !== null) {
            throw new \InvalidArgumentException('Realisasi untuk request ini sudah dikirim.');
        }

        $proofs = self::normalizeProofPaths($data['realization_proof_images'] ?? null);

        if ($proofs === []) {
            throw new \InvalidArgumentException('Minimal unggah satu bukti pembelian / pembayaran.');
        }

        $amount = (float) $data['realized_amount'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal realisasi harus lebih dari nol.');
        }

        $budgetRequest->loadMissing('employee');

        $employeeName = $budgetRequest->employee?->name ?? 'Karyawan';
        $notes = trim((string) ($data['realization_notes'] ?? ''));
        $desc = $budgetRequest->budget_name.' — '.$employeeName;
        if ($notes !== '') {
            $desc .= ' | '.$notes;
        } else {
            $desc .= ' | Realisasi anggaran';
        }

        $expenseDate = ! empty($data['expense_date'])
            ? \Carbon\Carbon::parse($data['expense_date'])->toDateString()
            : now()->toDateString();

        return DB::transaction(function () use ($budgetRequest, $amount, $notes, $proofs, $desc, $expenseDate) {
            $budgetRequest->update([
                'realized_amount' => $amount,
                'realization_notes' => $notes !== '' ? $notes : null,
                'realization_proof_images' => $proofs,
                'realization_submitted_at' => now(),
            ]);

            $expense = app(ExpenseRecognitionService::class)->ensureForBudgetRequest($budgetRequest->fresh());

            if (! $expense) {
                throw new \RuntimeException('Gagal mencatat pengeluaran untuk anggaran ini.');
            }

            $expense->update([
                'description' => $desc,
                'expense_date' => $expenseDate,
                'amount' => $amount,
                'proof_of_payment' => $proofs[0],
            ]);

            return $expense->fresh();
        });
    }

    /**
     * @param  array<int, string>|string|null  $raw
     * @return array<int, string>
     */
    private static function normalizeProofPaths(array|string|null $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            return [$raw];
        }

        return array_values(array_filter($raw, fn ($p) => is_string($p) && $p !== ''));
    }
}
