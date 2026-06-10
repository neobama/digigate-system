<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashbons', function (Blueprint $table) {
            $table->boolean('is_term_loan')->default(false)->after('installment_months');
        });
    }

    public function down(): void
    {
        Schema::table('cashbons', function (Blueprint $table) {
            $table->dropColumn('is_term_loan');
        });
    }
};
