<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('invoice_date');
        });
        
        // Backfill paid_date for existing invoices that are already paid/delivered
        // Set paid_date = invoice_date for historical data
        \DB::table('invoices')
            ->whereIn('status', ['paid', 'delivered'])
            ->whereNull('paid_date')
            ->update(['paid_date' => \DB::raw('invoice_date')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_date');
        });
    }
};
