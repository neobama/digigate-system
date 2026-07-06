<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('type', ['tap_in', 'tap_out'])
                ->default('tap_in')
                ->after('employee_id');
            $table->index(['employee_id', 'type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'type', 'recorded_at']);
            $table->dropColumn('type');
        });
    }
};
