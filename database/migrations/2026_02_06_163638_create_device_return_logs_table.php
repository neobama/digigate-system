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
        Schema::create('device_return_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_return_id');
            $table->string('status');
            $table->text('description')->nullable();
            $table->uuid('logged_by')->nullable(); // User ID (admin/karyawan)
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->foreign('device_return_id')
                ->references('id')
                ->on('device_returns')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_return_logs');
    }
};
