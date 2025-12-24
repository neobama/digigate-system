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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: Processor i7 11700K, RAM DDR4, dsb.
            $table->string('sn')->unique();
            $table->string('supplier');
            $table->date('purchase_date');
            $table->enum('status', ['available', 'used', 'warranty_claim'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
