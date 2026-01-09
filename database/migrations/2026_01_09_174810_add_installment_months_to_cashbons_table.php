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
        Schema::table('cashbons', function (Blueprint $table) {
            $table->integer('installment_months')->nullable()->after('status')->comment('Jumlah bulan cicilan (1-12), null = langsung dipotong');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbons', function (Blueprint $table) {
            $table->dropColumn('installment_months');
        });
    }
};
