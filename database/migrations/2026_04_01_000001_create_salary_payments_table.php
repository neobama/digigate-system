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
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('base_salary', 15, 2);
            $table->decimal('total_cashbon', 15, 2)->default(0);
            $table->decimal('bpjs_allowance', 15, 2)->default(0);
            $table->decimal('adjustment_addition', 15, 2)->default(0);
            $table->decimal('adjustment_deduction', 15, 2)->default(0);
            $table->text('adjustment_note')->nullable();
            $table->decimal('net_salary', 15, 2);
            $table->enum('fund_source', ['kas_kecil', 'bank_perusahaan'])->default('bank_perusahaan');
            $table->enum('status', ['draft', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->uuid('expense_id')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};

