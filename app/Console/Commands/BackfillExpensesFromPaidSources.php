<?php

namespace App\Console\Commands;

use App\Models\BudgetRequest;
use App\Models\Expense;
use App\Models\Reimbursement;
use App\Services\ExpenseRecognitionService;
use Illuminate\Console\Command;

class BackfillExpensesFromPaidSources extends Command
{
    protected $signature = 'expenses:backfill-from-paid-sources';

    protected $description = 'Create missing Expense rows for paid budget requests and reimbursements';

    public function handle(ExpenseRecognitionService $expenseRecognition): int
    {
        $budgetCreated = 0;
        $budgetSkipped = 0;
        $reimbCreated = 0;
        $reimbSkipped = 0;

        $paidBudgets = BudgetRequest::query()->where('status', 'paid')->get();
        $this->info('Budget requests paid: '.$paidBudgets->count());

        foreach ($paidBudgets as $budget) {
            $hadExpense = Expense::query()->where('budget_request_id', $budget->id)->exists();
            $expense = $expenseRecognition->ensureForBudgetRequest($budget);

            if ($expense && ! $hadExpense) {
                $budgetCreated++;
            } else {
                $budgetSkipped++;
            }
        }

        $paidReimbursements = Reimbursement::query()->where('status', 'paid')->get();
        $this->info('Reimbursements paid: '.$paidReimbursements->count());

        foreach ($paidReimbursements as $reimbursement) {
            $hadExpense = Expense::query()->where('reimbursement_id', $reimbursement->id)->exists();
            $expense = $expenseRecognition->ensureForReimbursement($reimbursement);

            if ($expense && ! $hadExpense) {
                $reimbCreated++;
            } else {
                $reimbSkipped++;
            }
        }

        $this->table(
            ['Source', 'Created', 'Skipped'],
            [
                ['Budget request', $budgetCreated, $budgetSkipped],
                ['Reimbursement', $reimbCreated, $reimbSkipped],
            ]
        );

        $this->info('Backfill selesai.');

        return self::SUCCESS;
    }
}
