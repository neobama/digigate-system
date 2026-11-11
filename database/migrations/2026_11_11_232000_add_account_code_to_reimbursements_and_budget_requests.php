<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('account_code')->nullable()->after('amount');
            $table->index('account_code');
        });

        Schema::table('budget_requests', function (Blueprint $table) {
            $table->string('account_code')->nullable()->after('amount');
            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropIndex(['account_code']);
            $table->dropColumn('account_code');
        });

        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropIndex(['account_code']);
            $table->dropColumn('account_code');
        });
    }
};
