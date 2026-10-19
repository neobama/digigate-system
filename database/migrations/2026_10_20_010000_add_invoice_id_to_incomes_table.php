<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable()->after('id');
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');
            $table->unique('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['invoice_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
    }
};
