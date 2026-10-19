<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->unique()->after('id');
            $table->string('invoice_number')->nullable()->after('ticket_number');
            $table->string('client_name')->nullable()->after('invoice_number');
            $table->string('phone_number')->nullable()->after('client_name');
            $table->string('device_type')->nullable()->after('phone_number');
            $table->text('complaint')->nullable()->after('device_type');
            $table->boolean('is_assembly_linked')->default(false)->after('assembly_serial_number');
            $table->string('source')->default('internal')->after('is_assembly_linked');
        });

        Schema::table('warranty_logs', function (Blueprint $table) {
            $table->string('action_type')->default('status_update')->after('status');
        });

        // MySQL enum extension for component damage status
        DB::statement("ALTER TABLE components MODIFY COLUMN status ENUM('available', 'used', 'warranty_claim', 'damaged') NOT NULL DEFAULT 'available'");

        // Backfill ticket numbers for existing claims
        $claims = DB::table('warranty_claims')->whereNull('ticket_number')->orderBy('created_at')->get();
        $seqByDay = [];
        foreach ($claims as $claim) {
            $day = \Carbon\Carbon::parse($claim->entry_date ?? $claim->created_at)->format('Ymd');
            $seqByDay[$day] = ($seqByDay[$day] ?? 0) + 1;
            $ticket = 'GS-'.$day.'-'.str_pad((string) $seqByDay[$day], 4, '0', STR_PAD_LEFT);
            DB::table('warranty_claims')->where('id', $claim->id)->update([
                'ticket_number' => $ticket,
                'source' => 'internal',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('warranty_logs', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });

        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_number',
                'invoice_number',
                'client_name',
                'phone_number',
                'device_type',
                'complaint',
                'is_assembly_linked',
                'source',
            ]);
        });

        DB::statement("ALTER TABLE components MODIFY COLUMN status ENUM('available', 'used', 'warranty_claim') NOT NULL DEFAULT 'available'");
    }
};
