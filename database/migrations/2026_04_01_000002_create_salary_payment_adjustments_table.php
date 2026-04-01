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
        Schema::create('salary_payment_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('salary_payment_id');
            $table->enum('type', ['addition', 'deduction']);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('salary_payment_id')
                ->references('id')
                ->on('salary_payments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payment_adjustments');
    }
};

