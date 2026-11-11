<?php

namespace App\Console\Commands;

use App\Models\Income;
use App\Models\Invoice;
use App\Services\IncomeRecognitionService;
use Illuminate\Console\Command;

class BackfillIncomesFromPaidInvoices extends Command
{
    protected $signature = 'incomes:backfill-from-paid-invoices';

    protected $description = 'Create/sync Income rows for partially paid, paid, and delivered invoices';

    public function handle(IncomeRecognitionService $incomeRecognition): int
    {
        $statuses = ['partially_paid', 'paid', 'delivered'];

        $invoices = Invoice::query()
            ->whereIn('status', $statuses)
            ->where('amount_paid', '>', 0)
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $this->info('Recognizable invoices: '.$invoices->count());

        foreach ($invoices as $invoice) {
            $hadIncome = Income::query()->where('invoice_id', $invoice->id)->exists();
            $income = $incomeRecognition->ensureForInvoice($invoice);

            if (! $income) {
                $skipped++;

                continue;
            }

            if ($hadIncome) {
                $updated++;
            } else {
                $created++;
            }
        }

        // Clean incomes linked to invoices that are no longer recognized
        $orphaned = Income::query()
            ->whereNotNull('invoice_id')
            ->whereDoesntHave('invoice', fn ($q) => $q->whereIn('status', $statuses)->where('amount_paid', '>', 0))
            ->count();

        if ($orphaned > 0) {
            Income::query()
                ->whereNotNull('invoice_id')
                ->whereDoesntHave('invoice', fn ($q) => $q->whereIn('status', $statuses)->where('amount_paid', '>', 0))
                ->delete();
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated/synced', $updated],
                ['Skipped', $skipped],
                ['Orphan incomes removed', $orphaned],
            ]
        );

        $this->info('Backfill selesai.');

        return self::SUCCESS;
    }
}
