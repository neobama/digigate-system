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
        Schema::create('device_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_number')->unique();
            $table->string('invoice_number');
            $table->date('purchase_date');
            $table->enum('device_type', [
                'Kasuari 6G 2S+',
                'Maleo 6G 4S+',
                'Macan 6G 4S+',
                'Komodo 8G 4S+ 2QS28'
            ]);
            $table->string('serial_number');
            $table->boolean('include_mikrotik_license')->default(false);
            $table->string('customer_name');
            $table->string('company_name')->nullable();
            $table->string('phone_number');
            $table->text('issue_details');
            $table->json('proof_files')->nullable(); // Array of file paths
            $table->enum('status', [
                'pending',
                'received',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_returns');
    }
};
