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
        Schema::create('logbook_employees', function (Blueprint $table) {
            $table->uuid('logbook_id');
            $table->uuid('employee_id');
            $table->timestamps();
            
            $table->primary(['logbook_id', 'employee_id']);
            $table->foreign('logbook_id')->references('id')->on('logbooks')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbook_employees');
    }
};
