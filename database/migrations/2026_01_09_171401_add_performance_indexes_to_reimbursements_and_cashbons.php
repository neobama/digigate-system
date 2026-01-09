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
        Schema::table('reimbursements', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            $table->index('status', 'idx_reimbursements_status');
            $table->index('expense_date', 'idx_reimbursements_expense_date');
            $table->index(['status', 'expense_date'], 'idx_reimbursements_status_date');
        });

        Schema::table('cashbons', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            $table->index('status', 'idx_cashbons_status');
            $table->index('request_date', 'idx_cashbons_request_date');
            $table->index(['status', 'request_date'], 'idx_cashbons_status_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropIndex('idx_reimbursements_status');
            $table->dropIndex('idx_reimbursements_expense_date');
            $table->dropIndex('idx_reimbursements_status_date');
        });

        Schema::table('cashbons', function (Blueprint $table) {
            $table->dropIndex('idx_cashbons_status');
            $table->dropIndex('idx_cashbons_request_date');
            $table->dropIndex('idx_cashbons_status_date');
        });
    }
};
