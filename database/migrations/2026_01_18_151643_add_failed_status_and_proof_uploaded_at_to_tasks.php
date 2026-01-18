<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'failed' status to enum
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled', 'failed') DEFAULT 'pending'");
        
        // Add proof_uploaded_at to task_employee pivot table
        Schema::table('task_employee', function (Blueprint $table) {
            $table->timestamp('proof_uploaded_at')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'failed' status from enum
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
        
        Schema::table('task_employee', function (Blueprint $table) {
            $table->dropColumn('proof_uploaded_at');
        });
    }
};
