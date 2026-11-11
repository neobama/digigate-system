<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_paid', 15, 2)->default(0)->after('total_amount');
        });

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('proforma', 'partially_paid', 'paid', 'delivered', 'cancelled') NOT NULL DEFAULT 'proforma'");

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });

        // Backfill: existing paid/delivered invoices count as fully paid once.
        $invoices = DB::table('invoices')
            ->whereIn('status', ['paid', 'delivered'])
            ->get(['id', 'total_amount', 'paid_date', 'created_at']);

        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->total_amount;
            if ($amount <= 0) {
                continue;
            }

            DB::table('invoice_payments')->insert([
                'id' => (string) Str::uuid(),
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'paid_at' => $invoice->paid_date ?: ($invoice->created_at ? substr((string) $invoice->created_at, 0, 10) : now()->toDateString()),
                'method' => 'backfill',
                'notes' => 'Backfill dari status lunas sebelumnya',
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([
                'amount_paid' => $amount,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');

        DB::table('invoices')->where('status', 'partially_paid')->update(['status' => 'proforma']);

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('proforma', 'paid', 'delivered', 'cancelled') NOT NULL DEFAULT 'proforma'");

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
