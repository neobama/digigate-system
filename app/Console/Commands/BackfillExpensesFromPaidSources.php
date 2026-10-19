<?php

namespace App\Console\Commands;

use App\Models\BudgetRequest;
use App\Models\Cashbon;
use App\Models\Expense;
use App\Models\Reimbursement;
use App\Services\ExpenseRecognitionService;
use Illuminate\Console\Command;

class BackfillExpensesFromPaidSources extends Command
{
    protected $signature = 'expenses:backfill-from-paid-sources';

    protected $description = 'Create/sync Expense rows for paid cashbon, budget requests, and reimbursements; remove orphans';

    public function handle(ExpenseRecognitionService $expenseRecognition): int
    {
        $stats = [
            'cashbon' => ['created' => 0, 'synced' => 0, 'skipped' => 0],
            'budget' => ['created' => 0, 'synced' => 0, 'skipped' => 0],
            'reimbursement' => ['created' => 0, 'synced' => 0, 'skipped' => 0],
        ];

        $paidCashbons = Cashbon::query()->where('status', 'paid')->get();
        $this->info('Cashbon paid: '.$paidCashbons->count());

        foreach ($paidCashbons as $cashbon) {
            $had = Expense::query()->where('cashbon_id', $cashbon->id)->exists();
            $expense = $expenseRecognition->ensureForCashbon($cashbon);

            if (! $expense) {
                $stats['cashbon']['skipped']++;
            } elseif ($had) {
                $stats['cashbon']['synced']++;
            } else {
                $stats['cashbon']['created']++;
            }
        }

        $paidBudgets = BudgetRequest::query()->where('status', 'paid')->get();
        $this->info('Budget requests paid: '.$paidBudgets->count());

        foreach ($paidBudgets as $budget) {
            $had = Expense::query()->where('budget_request_id', $budget->id)->exists();
            $expense = $expenseRecognition->ensureForBudgetRequest($budget);

            if (! $expense) {
                $stats['budget']['skipped']++;
            } elseif ($had) {
                $stats['budget']['synced']++;
            } else {
                $stats['budget']['created']++;
            }
        }

        $paidReimbursements = Reimbursement::query()->where('status', 'paid')->get();
        $this->info('Reimbursements paid: '.$paidReimbursements->count());

        foreach ($paidReimbursements as $reimbursement) {
            $had = Expense::query()->where('reimbursement_id', $reimbursement->id)->exists();
            $expense = $expenseRecognition->ensureForReimbursement($reimbursement);

            if (! $expense) {
                $stats['reimbursement']['skipped']++;
            } elseif ($had) {
                $stats['reimbursement']['synced']++;
            } else {
                $stats['reimbursement']['created']++;
            }
        }

        // Remove expenses linked to sources that are no longer paid
        $orphanedCashbon = Expense::query()
            ->whereNotNull('cashbon_id')
            ->whereDoesntHave('cashbon', fn ($q) => $q->where('status', 'paid'))
            ->count();
        Expense::query()
            ->whereNotNull('cashbon_id')
            ->whereDoesntHave('cashbon', fn ($q) => $q->where('status', 'paid'))
            ->delete();

        $orphanedBudget = Expense::query()
            ->whereNotNull('budget_request_id')
            ->whereDoesntHave('budgetRequest', fn ($q) => $q->where('status', 'paid'))
            ->count();
        Expense::query()
            ->whereNotNull('budget_request_id')
            ->whereDoesntHave('budgetRequest', fn ($q) => $q->where('status', 'paid'))
            ->delete();

        $orphanedReimb = Expense::query()
            ->whereNotNull('reimbursement_id')
            ->whereDoesntHave('reimbursement', fn ($q) => $q->where('status', 'paid'))
            ->count();
        Expense::query()
            ->whereNotNull('reimbursement_id')
            ->whereDoesntHave('reimbursement', fn ($q) => $q->where('status', 'paid'))
            ->delete();

        $this->table(
            ['Source', 'Created', 'Synced', 'Skipped'],
            [
                ['Cashbon', $stats['cashbon']['created'], $stats['cashbon']['synced'], $stats['cashbon']['skipped']],
                ['Budget request', $stats['budget']['created'], $stats['budget']['synced'], $stats['budget']['skipped']],
                ['Reimbursement', $stats['reimbursement']['created'], $stats['reimbursement']['synced'], $stats['reimbursement']['skipped']],
            ]
        );

        $this->table(
            ['Orphans removed', 'Count'],
            [
                ['Cashbon-linked', $orphanedCashbon],
                ['Budget-linked', $orphanedBudget],
                ['Reimbursement-linked', $orphanedReimb],
            ]
        );

        $this->info('Backfill selesai.');

        return self::SUCCESS;
    }
}
