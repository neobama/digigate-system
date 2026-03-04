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
        Schema::create('warranty_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warranty_claim_id')->constrained('warranty_claims')->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled']);
            $table->string('old_component_sn')->nullable(); // SN component lama yang diganti
            $table->string('new_component_sn')->nullable(); // SN component baru yang dipasang
            $table->string('component_type')->nullable(); // processor, ram_1, ram_2, ssd, chassis
            $table->text('notes')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('warranty_claim_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranty_logs');
    }
};
