<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: This migration recreates tables with UUID. Existing data will be lost.
     * For production, you should backup data first and migrate it manually.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate tables
        if (DB::getDriverName() === 'sqlite') {
            $this->recreateTablesForSqlite();
        } else {
            $this->alterTablesForOtherDatabases();
        }
    }

    protected function recreateTablesForSqlite(): void
    {
        // Disable foreign keys
        DB::statement('PRAGMA foreign_keys=OFF;');

        // 1. Recreate users table
        $this->recreateUsersTable();
        
        // 2. Recreate invoices table
        $this->recreateInvoicesTable();
        
        // 3. Recreate components table
        $this->recreateComponentsTable();
        
        // 4. Recreate employees table
        $this->recreateEmployeesTable();
        
        // 5. Recreate assemblies table
        $this->recreateAssembliesTable();
        
        // 6. Recreate logbooks table
        $this->recreateLogbooksTable();
        
        // 7. Recreate cashbons table
        $this->recreateCashbonsTable();

        // Re-enable foreign keys
        DB::statement('PRAGMA foreign_keys=ON;');
    }

    protected function recreateUsersTable(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function recreateInvoicesTable(): void
    {
        Schema::dropIfExists('invoices');
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

    protected function recreateComponentsTable(): void
    {
        Schema::dropIfExists('components');
        Schema::create('components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('sn')->unique();
            $table->string('supplier');
            $table->date('purchase_date');
            $table->enum('status', ['available', 'used', 'warranty_claim'])->default('available');
            $table->timestamps();
        });
    }

    protected function recreateEmployeesTable(): void
    {
        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nik')->unique();
            $table->uuid('user_id')->nullable();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->string('position');
            $table->decimal('base_salary', 15, 2);
            $table->decimal('bpjs_allowance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    protected function recreateAssembliesTable(): void
    {
        Schema::dropIfExists('assemblies');
        Schema::create('assemblies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('product_type');
            $table->json('sn_details');
            $table->date('assembly_date');
            $table->string('serial_number')->unique()->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });
    }

    protected function recreateLogbooksTable(): void
    {
        Schema::dropIfExists('logbooks');
        Schema::create('logbooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->date('log_date');
            $table->text('activity');
            $table->json('photo')->nullable();
            $table->timestamps();
            
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    protected function recreateCashbonsTable(): void
    {
        Schema::dropIfExists('cashbons');
        Schema::create('cashbons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->date('request_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid_off'])->default('pending');
            $table->timestamps();
            
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    protected function alterTablesForOtherDatabases(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Similar logic but using ALTER TABLE for MySQL/PostgreSQL
        // This is more complex and would need to preserve existing data
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting UUID to integer ID is complex
        // You may need to manually recreate tables with integer IDs
    }
};
