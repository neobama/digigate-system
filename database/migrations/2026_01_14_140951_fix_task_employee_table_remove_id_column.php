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
        // For MySQL, we need to modify the id column to have a default value
        // Since UUID() is not available as default in MySQL, we'll use a trigger
        // Or we can make it nullable temporarily and then add a trigger
        
        // Option 1: Make id nullable and use a trigger (more complex)
        // Option 2: Remove id column and use composite primary key (simpler)
        
        // Let's go with Option 2 - remove id and use composite primary key
        Schema::table('task_employee', function (Blueprint $table) {
            // Drop the unique constraint first if it exists
            $table->dropUnique(['task_id', 'employee_id']);
        });
        
        // Drop primary key constraint (MySQL specific)
        DB::statement('ALTER TABLE task_employee DROP PRIMARY KEY');
        
        Schema::table('task_employee', function (Blueprint $table) {
            // Drop the id column
            $table->dropColumn('id');
            
            // Add composite primary key
            $table->primary(['task_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_employee', function (Blueprint $table) {
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
        });
    }
};
