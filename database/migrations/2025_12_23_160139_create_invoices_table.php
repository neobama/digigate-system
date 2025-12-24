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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->string('client_name');
            $table->string('po_number')->nullable();
            $table->date('invoice_date');
            $table->decimal('total_amount', 15, 2);
            $table->json('items')->nullable();
            $table->decimal('shipping_cost', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->default(0)->nullable();
            $table->enum('status', ['proforma', 'paid', 'delivered', 'cancelled'])->default('proforma');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
