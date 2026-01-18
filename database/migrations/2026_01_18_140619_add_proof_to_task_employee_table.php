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
        Schema::table('task_employee', function (Blueprint $table) {
            $table->json('proof_images')->nullable()->after('employee_id');
            $table->text('notes')->nullable()->after('proof_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_employee', function (Blueprint $table) {
            $table->dropColumn(['proof_images', 'notes']);
        });
    }
};
