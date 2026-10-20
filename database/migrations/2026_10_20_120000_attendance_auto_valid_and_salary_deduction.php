<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->text('change_request_note')->nullable()->after('admin_notes');
            $table->timestamp('proposed_recorded_at')->nullable()->after('change_request_note');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('days_present')->default(0)->after('bpjs_allowance');
            $table->decimal('attendance_deduction', 15, 2)->default(0)->after('days_present');
        });

        // Absensi lama yang menunggu verifikasi dianggap sah (kecuali ditolak).
        DB::table('attendances')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'verified_at' => DB::raw('COALESCE(verified_at, recorded_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            $table->dropColumn(['days_present', 'attendance_deduction']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['change_request_note', 'proposed_recorded_at']);
        });
    }
};
