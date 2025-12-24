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
        // For SQLite, we need to recreate the table to change enum
        if (DB::getDriverName() === 'sqlite') {
            // Disable foreign keys
            DB::statement('PRAGMA foreign_keys=OFF;');
            
            // Backup existing data
            $cashbons = DB::table('cashbons')->get();
            
            // Recreate table with new enum
            Schema::dropIfExists('cashbons');
            Schema::create('cashbons', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->decimal('amount', 15, 2);
                $table->string('reason');
                $table->date('request_date');
                $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
                $table->timestamps();
                
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
            
            // Restore data with updated status
            foreach ($cashbons as $cashbon) {
                $status = $cashbon->status === 'paid_off' ? 'paid' : $cashbon->status;
                DB::table('cashbons')->insert([
                    'id' => $cashbon->id,
                    'employee_id' => $cashbon->employee_id,
                    'amount' => $cashbon->amount,
                    'reason' => $cashbon->reason,
                    'request_date' => $cashbon->request_date,
                    'status' => $status,
                    'created_at' => $cashbon->created_at,
                    'updated_at' => $cashbon->updated_at,
                ]);
            }
            
            // Re-enable foreign keys
            DB::statement('PRAGMA foreign_keys=ON;');
        } else {
            // For MySQL/PostgreSQL, update data first
            DB::table('cashbons')
                ->where('status', 'paid_off')
                ->update(['status' => 'paid']);
            
            // Then alter enum (MySQL specific)
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE cashbons MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'paid_off'
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF;');
            
            $cashbons = DB::table('cashbons')->get();
            
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
            
            foreach ($cashbons as $cashbon) {
                $status = $cashbon->status === 'paid' ? 'paid_off' : $cashbon->status;
                DB::table('cashbons')->insert([
                    'id' => $cashbon->id,
                    'employee_id' => $cashbon->employee_id,
                    'amount' => $cashbon->amount,
                    'reason' => $cashbon->reason,
                    'request_date' => $cashbon->request_date,
                    'status' => $status,
                    'created_at' => $cashbon->created_at,
                    'updated_at' => $cashbon->updated_at,
                ]);
            }
            
            DB::statement('PRAGMA foreign_keys=ON;');
        } else {
            DB::table('cashbons')
                ->where('status', 'paid')
                ->update(['status' => 'paid_off']);
            
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE cashbons MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'paid_off') DEFAULT 'pending'");
            }
        }
    }
};
