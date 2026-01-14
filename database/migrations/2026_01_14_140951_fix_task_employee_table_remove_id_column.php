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
        // Drop foreign key constraints first
        Schema::table('task_employee', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });
        
        // Drop the unique constraint
        Schema::table('task_employee', function (Blueprint $table) {
            $table->dropUnique(['task_id', 'employee_id']);
        });
        
        // Drop primary key constraint (MySQL specific)
        DB::statement('ALTER TABLE task_employee DROP PRIMARY KEY');
        
        Schema::table('task_employee', function (Blueprint $table) {
            // Drop the id column
            $table->dropColumn('id');
            
            // Add composite primary key
            $table->primary(['task_id', 'employee_id']);
            
            // Re-add foreign key constraints
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_employee', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
            
            // Drop composite primary key
            $table->dropPrimary(['task_id', 'employee_id']);
            
            // Add back the id column (nullable first)
            $table->uuid('id')->nullable()->first();
        });
        
        // Generate UUIDs for existing records
        DB::statement('UPDATE task_employee SET id = UUID() WHERE id IS NULL');
        
        // Make id not nullable and add primary key
        Schema::table('task_employee', function (Blueprint $table) {
            $table->uuid('id')->nullable(false)->change();
        });
        
        DB::statement('ALTER TABLE task_employee ADD PRIMARY KEY (id)');
        
        Schema::table('task_employee', function (Blueprint $table) {
            // Add back unique constraint
            $table->unique(['task_id', 'employee_id']);
            
            // Re-add foreign key constraints
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }
};
