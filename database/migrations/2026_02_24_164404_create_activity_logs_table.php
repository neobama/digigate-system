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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_name')->nullable();
            $table->string('action'); // create, update, delete, view, login, logout, etc
            $table->string('description'); // Human readable description
            $table->string('model_type')->nullable(); // App\Models\Task, etc
            $table->uuid('model_id')->nullable(); // ID of the model
            $table->json('old_values')->nullable(); // Old values before update
            $table->json('new_values')->nullable(); // New values after update
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable(); // Request URL
            $table->string('method')->nullable(); // GET, POST, PUT, DELETE
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
